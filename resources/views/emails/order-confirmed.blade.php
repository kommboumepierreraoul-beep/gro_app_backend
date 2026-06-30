<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Confirmation commande</title></head>
<body style="font-family: Arial, sans-serif;">
<h2>✅ Votre commande est confirmée</h2>
<p>Bonjour {{ $order->user->firstname ?? 'Client' }},</p>
<p>Votre commande n° <strong>{{ $order->order_number }}</strong> d’un montant total de <strong>{{ number_format($order->total_amount, 0, ',', ' ') }} FCFA</strong> a bien été payée.</p>
<p><strong>Produits commandés :</strong></p>
<ul>
@foreach($order->items as $item)
    <li>{{ $item->quantity }} x {{ $item->product->name ?? 'Produit' }} — {{ number_format($item->unit_price * $item->quantity, 0, ',', ' ') }} FCFA</li>
@endforeach
</ul>
<p>Nous vous tiendrons informé de son expédition.</p>
<p>Merci de votre confiance.</p>
</body>
</html>
