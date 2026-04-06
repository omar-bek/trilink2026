<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Contract {{ $contract->contract_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { text-align: center; color: #333; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .section { margin-bottom: 15px; }
        .section-title { font-weight: bold; font-size: 14px; color: #555; margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }
        .signatures { margin-top: 30px; }
        .signature-box { display: inline-block; width: 45%; margin: 10px; border-top: 1px solid #333; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRACT</h1>
        <p><strong>Contract Number:</strong> {{ $contract->contract_number }}</p>
        <p><strong>Date:</strong> {{ $contract->created_at->format('Y-m-d') }}</p>
        <p><strong>Status:</strong> {{ $contract->status->value }}</p>
    </div>

    <div class="section">
        <div class="section-title">Title</div>
        <p>{{ $contract->title }}</p>
    </div>

    @if($contract->description)
    <div class="section">
        <div class="section-title">Description</div>
        <p>{{ $contract->description }}</p>
    </div>
    @endif

    <div class="section">
        <div class="section-title">Buyer</div>
        <p>{{ $contract->buyerCompany->name }}</p>
    </div>

    <div class="section">
        <div class="section-title">Parties</div>
        <table>
            <tr><th>Company</th><th>Role</th></tr>
            @foreach($contract->parties ?? [] as $party)
            <tr>
                <td>{{ $party['company_id'] ?? 'N/A' }}</td>
                <td>{{ $party['role'] ?? 'N/A' }}</td>
            </tr>
            @endforeach
        </table>
    </div>

    <div class="section">
        <div class="section-title">Financial Details</div>
        <p><strong>Total Amount:</strong> {{ number_format($contract->total_amount, 2) }} {{ $contract->currency }}</p>
        @if($contract->start_date)
        <p><strong>Start Date:</strong> {{ $contract->start_date->format('Y-m-d') }}</p>
        @endif
        @if($contract->end_date)
        <p><strong>End Date:</strong> {{ $contract->end_date->format('Y-m-d') }}</p>
        @endif
    </div>

    @if($contract->terms)
    <div class="section">
        <div class="section-title">Terms & Conditions</div>
        <p>{{ $contract->terms }}</p>
    </div>
    @endif

    <div class="signatures">
        <div class="section-title">Signatures</div>
        @foreach($contract->signatures ?? [] as $sig)
        <div class="signature-box">
            <p>Signed at: {{ is_array($sig) ? ($sig['signed_at'] ?? 'N/A') : 'N/A' }}</p>
        </div>
        @endforeach
    </div>

    <p style="text-align: center; margin-top: 40px; font-size: 10px; color: #999;">
        Version {{ $contract->version }} | Generated on {{ now()->format('Y-m-d H:i:s') }}
    </p>
</body>
</html>
