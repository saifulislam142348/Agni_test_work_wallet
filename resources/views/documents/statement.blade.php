<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Wallet Statement</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #E2136E; } /* bKash Magenta */
        .details { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .credit { color: green; }
        .debit { color: red; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Transaction Statement</h1>
        <p>Generated on: {{ $date }}</p>
    </div>

    <div class="details">
        <strong>User Name:</strong> {{ $user->name }} <br>
        <strong>Email:</strong> {{ $user->email }} <br>
        <strong>Wallet ID:</strong> {{ $user->wallet->id ?? 'N/A' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Trx ID</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Balance After</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $trx)
            <tr>
                <td>{{ $trx->created_at->format('d M Y, h:i A') }}</td>
                <td>{{ $trx->trx_id }}</td>
                <td>
                    <span class="{{ $trx->type == 'credit' ? 'credit' : 'debit' }}">
                        {{ ucfirst($trx->type) }}
                    </span>
                </td>
                <td>{{ number_format($trx->amount, 2) }}</td>
                <td>{{ number_format($trx->balance_after, 2) }}</td>
                <td>{{ $trx->description }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
