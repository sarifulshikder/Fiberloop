<?php

namespace App\Services\Network;

use App\Models\NetworkDevice;
use RuntimeException;
use Throwable;

/**
 * Stateful telnet client for OLTs whose CLI is only reachable over plain
 * telnet (VSOL EPON/GPON) rather than SSH.
 *
 * The session is established once per transport: banner -> login -> enable ->
 * `terminal length 0` (paging off). exec() accepts a multi-line command
 * sequence such as "configure terminal\ninterface epon 0/1\nshow onu
 * status\nexit\nexit"; each line is sent in turn and only the output of the
 * last command that produced any is returned, so a driver can express
 * "navigate into context, run command, exit" as a single command.
 */
class TelnetTransport
{
    private const PROMPT = 'epon-olt(?:\(config(?:-[^)]*)?\))?[#>]';

    /** @var resource|null */
    private $socket = null;

    public function __construct(
        protected NetworkDevice $device,
        protected ?int $port = null,
        protected ?string $enablePassword = null,
        protected float $timeout = 10.0,
    ) {
    }

    /**
     * Open the telnet session and negotiate login, enable and paging.
     */
    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }

        $host = $this->device->ip_address;
        $port = $this->port ?? 23;

        $socket = @stream_socket_client(
            'tcp://' . $host . ':' . $port,
            $errno,
            $errstr,
            $this->timeout
        );

        if ($socket === false) {
            throw new RuntimeException(
                "Telnet connect failed for device {$this->device->id} ({$host}:{$port}): {$errstr}"
            );
        }

        $this->socket = $socket;

        try {
            $this->readUntil('/login:\s*$/i');
            $this->writeLine((string) $this->device->username);
            $this->readUntil('/password:\s*$/i');
            $this->writeLine((string) $this->device->password);
            $this->readUntilPrompt();

            $this->writeLine('enable');
            $matched = $this->readUntil(['/password:\s*$/i', '/^.*' . self::PROMPT . '\s*$/m']);
            if ($matched === 0) {
                $this->writeLine($this->enablePassword ?? (string) $this->device->password);
                $this->readUntilPrompt();
            }

            $this->writeLine('terminal length 0');
            $this->readUntilPrompt();
        } catch (Throwable $e) {
            $this->disconnect();

            throw new RuntimeException(
                "Telnet login failed for {$host}:{$port} ({$e->getMessage()})",
                0,
                $e
            );
        }
    }

    public function disconnect(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }

        $this->socket = null;
    }

    public function isConnected(): bool
    {
        return is_resource($this->socket) && !feof($this->socket);
    }

    /**
     * Run a command (possibly a multi-line navigation sequence) and return the
     * output of the last command that produced any, stripped of echoes/prompts.
     */
    public function exec(string $command): string
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        $output = '';

        foreach (preg_split('/\r?\n/', trim($command)) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $this->writeLine($line);
            $buffer = $this->readUntilPrompt();
            $clean = $this->stripOutput($buffer, $line);

            if ($clean !== '') {
                $output = $clean;
            }
        }

        return $output;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function writeLine(string $line): void
    {
        $written = @fwrite($this->socket, $line . "\r\n");

        if ($written === false || $written < strlen($line) + 2) {
            throw new RuntimeException('Telnet write failed');
        }
    }

    private string $readBuffer = '';

    private function readUntil(string|array $patterns): int
    {
        $patterns = (array) $patterns;
        $this->readBuffer = '';
        $deadline = microtime(true) + $this->timeout;

        while (microtime(true) < $deadline) {
            $chunk = $this->readChunk(max(0.1, $deadline - microtime(true)));

            if ($chunk !== '') {
                $this->readBuffer .= $chunk;
            }

            foreach ($patterns as $index => $pattern) {
                if (preg_match($pattern, $this->readBuffer)) {
                    return $index;
                }
            }
        }

        throw new RuntimeException(
            'Timed out waiting for response, got: ' . var_export($this->readBuffer, true)
        );
    }

    private function readUntilPrompt(): string
    {
        $pattern = '/^.*' . self::PROMPT . '\s*$/m';

        $this->readUntil($pattern);

        $buffer = $this->readBuffer;
        $this->readBuffer = '';

        return $buffer;
    }

    private function readChunk(float $timeout): string
    {
        $read = [$this->socket];
        $write = null;
        $except = null;

        $seconds = (int) floor($timeout);
        $micros = (int) (($timeout - $seconds) * 1_000_000);

        if (@stream_select($read, $write, $except, $seconds, $micros) !== 1) {
            return '';
        }

        $chunk = @fread($this->socket, 8192);

        if ($chunk === false || $chunk === '') {
            return '';
        }

        return $this->sanitize($chunk);
    }

    /**
     * Remove telnet IAC negotiation bytes (and sub-negotiation bodies) from a
     * read chunk so they never leak into parsed output.
     */
    private function sanitize(string $data): string
    {
        $out = '';
        $length = strlen($data);

        for ($i = 0; $i < $length; $i++) {
            $byte = ord($data[$i]);

            if ($byte !== 0xFF) { // IAC
                $out .= $data[$i];

                continue;
            }

            $i++;

            if ($i >= $length) {
                break;
            }

            if (ord($data[$i]) === 0xFA) { // SB - skip until IAC SE
                while ($i + 1 < $length) {
                    $i++;

                    if (ord($data[$i]) === 0xFF && $i + 1 < $length && ord($data[$i + 1]) === 0xF0) {
                        $i++;

                        break;
                    }
                }
            }
        }

        return $out;
    }

    private function stripOutput(string $buffer, string $command): string
    {
        $out = preg_replace('/\s*' . self::PROMPT . '\s*$/s', '', $buffer);
        $out = preg_replace('/^\s*' . preg_quote(trim($command), '/') . '(?:\r\n|\r|\n)?/', '', (string) $out);

        return trim((string) $out);
    }
}
