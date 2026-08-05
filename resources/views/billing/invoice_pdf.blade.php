<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: bold;
            margin: 0 0 10px 0;
        }
        
        .header h2 {
            font-size: 18px;
            margin: 0 0 5px 0;
        }
        
        .company-info {
            text-align: center;
            font-size: 11px;
            margin-bottom: 20px;
        }
        
        .invoice-title {
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
        }
        
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .invoice-details table {
            width: 100%;
        }
        
        .invoice-details td {
            padding: 5px;
            vertical-align: top;
        }
        
        .customer-info {
            margin-bottom: 20px;
        }
        
        .customer-info h3 {
            font-size: 14px;
            margin-bottom: 10px;
            background: #f5f5f5;
            padding: 8px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .items-table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        
        .items-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .amount {
            text-align: right;
        }
        
        .totals {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .totals td {
            padding: 8px;
            text-align: right;
            border: none;
        }
        
        .totals .label {
            text-align: right;
            font-weight: bold;
            padding-right: 10px;
        }
        
        .total-row {
            border-top: 2px solid #333;
            font-weight: bold;
            font-size: 14px;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        
        .notes {
            margin-top: 20px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            color: white;
        }
        
        .status-draft { background: #ccc; }
        .status-sent { background: #2196F3; }
        .status-paid { background: #4CAF50; }
        .status-partial { background: #FF9800; }
        .status-overdue { background: #F44336; }
        .status-void { background: #9E9E9E; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>{{ $companyName }}</h1>
            <h2>ISP Billing & Internet Services</h2>
            <div class="company-info">
                {!! nl2br(e($companyAddress)) !!}<br>
                {!! nl2br(e($companyContact)) !!}
            </div>
        </div>

        <!-- Invoice Title -->
        <div class="invoice-title">
            INVOICE
        </div>

        <!-- Invoice Details -->
        <div class="invoice-details">
            <table>
                <tr>
                    <td width="60%"><strong>Invoice No:</strong> {{ $invoice->invoice_number }}</td>
                    <td width="40%" style="text-align: right;"><strong>Date:</strong> {{ $invoice->period_start->format('d M Y') }} - {{ $invoice->period_end->format('d M Y') }}</td>
                </tr>
                <tr>
                    <td><strong>Due Date:</strong> {{ $invoice->due_date->format('d M Y') }}</td>
                    <td style="text-align: right;"><strong>Status:</strong> 
                        <span class="status-badge status-{{ strtolower($invoice->status->value) }}">
                            {{ ucfirst(strtolower($invoice->status->value)) }}
                        </span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Customer Information -->
        <div class="customer-info">
            <h3>BILL TO:</h3>
            <strong>{{ $customer->full_name }}</strong><br>
            Phone: {{ $customer->phone }}<br>
            Email: {{ $customer->email ?? 'N/A' }}<br>
            Address: {{ $customer->address ?? 'N/A' }}
        </div>

        <!-- Subscription Information -->
        @if($invoice->subscription)
        <div class="customer-info">
            <h3>SUBSCRIPTION DETAILS:</h3>
            Package: {{ $invoice->subscription->package->name ?? 'N/A' }}<br>
            Connection Type: {{ $invoice->subscription->connection_type->value ?? 'N/A' }}<br>
            @if($invoice->subscription->ip_address)
                IP Address: {{ $invoice->subscription->ip_address }}<br>
            @endif
        </div>
        @endif

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="65%">Description</th>
                    <th width="10%">Quantity</th>
                    <th width="10%">Unit Price</th>
                    <th width="10%">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td class="amount">BDT {{ number_format($item->unit_price / 100, 2) }}</td>
                    <td class="amount">BDT {{ number_format($item->amount / 100, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <table class="totals">
            <tr>
                <td class="label">Subtotal:</td>
                <td>BDT {{ number_format($invoice->subtotal / 100, 2) }}</td>
            </tr>
            <tr>
                <td class="label">VAT ({{ $invoice->tax_rate ?? config('billing.tax_rate', 15) }}%):</td>
                <td>BDT {{ number_format($invoice->tax_amount / 100, 2) }}</td>
            </tr>
            @if($invoice->discount_amount > 0)
            <tr>
                <td class="label">Discount:</td>
                <td>-BDT {{ number_format($invoice->discount_amount / 100, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td class="label">TOTAL:</td>
                <td>BDT {{ number_format($invoice->total / 100, 2) }}</td>
            </tr>
            @if($invoice->paid_amount > 0)
            <tr>
                <td class="label">Paid:</td>
                <td>BDT {{ number_format($invoice->paid_amount / 100, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Outstanding:</td>
                <td>BDT {{ number_format($invoice->outstanding_amount / 100, 2) }}</td>
            </tr>
            @endif
        </table>

        <!-- Notes -->
        @if($invoice->notes)
        <div class="notes">
            <strong>Notes:</strong> {{ $invoice->notes }}
        </div>
        @endif

        <!-- Payment Instructions -->
        <div class="notes" style="margin-top: 20px;">
            <strong>Payment Instructions:</strong><br>
            Please pay by the due date to avoid service interruption.<br>
            Payment methods: bKash, Nagad, Bank Transfer, Cash at Office
        </div>

        <!-- Footer -->
        <div class="footer">
            Thank you for your business!<br>
            This is a computer-generated invoice. No signature required.
        </div>
    </div>
</body>
</html>