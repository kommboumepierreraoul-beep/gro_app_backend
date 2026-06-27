<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Facture #{{ $order->order_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .invoice-box { max-width: 800px; margin: auto; padding: 20px; border: 1px solid #ddd; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #0b5e2e; }
        .company-info { text-align: right; font-size: 11px; }
        .client-info { margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .total { text-align: right; margin-top: 20px; font-weight: bold; }
        .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #888; }
        .signature { margin-top: 30px; border-top: 1px solid #ddd; padding-top: 10px; font-size: 10px; }
    </style>
</head>
<body>
<div class="invoice-box">
    <div class="header"><h1>AgriConnect</h1><p>Facture officielle</p></div>
    <div class="company-info">
        <strong>AgriConnect SARL</strong><br>Yaoundé, Cameroun<br>NIF: 123456789<br>Email: contact@agriconnect.cm
    </div>
    <div class="client-info">
        <strong>Facturé à :</strong><br>{{ $order->user->name ?? $order->user->email }}<br>{{ $order->shipping_address }}
    </div>
    <div class="invoice-details">
        <p><strong>N° Facture :</strong> INV-{{ $order->id }}-{{ $order->order_number }}</p>
        <p><strong>Date :</strong> {{ now()->format('d/m/Y H:i') }}</p>
        <p><strong>Commande :</strong> #{{ $order->order_number }}</p>
    </div>
    <table>
        <thead><tr><th>Produit</th><th>Qté</th><th>Prix unitaire</th><th>Total</th></tr></thead>
        <tbody>
            @foreach($order->items as $item)
            <tr><td>{{ $item->product->name ?? 'Produit' }}</td><td>{{ $item->quantity }}</td><td>{{ number_format($item->unit_price,0,',',' ') }} FCFA</td><td>{{ number_format($item->unit_price*$item->quantity,0,',',' ') }} FCFA</td></tr>
            @endforeach
        </tbody>
    </table>
    <div class="total">Total : {{ number_format($order->total_amount,0,',',' ') }} FCFA</div>
    <div class="signature"><div style="float:left;">Cachet et signature</div><div style="float:right;">Fait à Yaoundé, le {{ now()->format('d/m/Y') }}</div><div style="clear:both;"></div></div>
    <div class="footer">Cette facture est générée électroniquement et fait foi.<br>Facture sécurisée - Toute falsification est détectable.</div>
</div>
</body>
</html>
