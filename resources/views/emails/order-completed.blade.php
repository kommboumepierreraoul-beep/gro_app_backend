<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Commande finalisée</title></head>
<body style="font-family: Arial, sans-serif;">
<h2>🎉 Commande terminée</h2>
<p>Bonjour {{ $order->user->firstname ?? 'Client' }},</p>
<p>Votre commande n° <strong>{{ $order->order_number }}</strong> est désormais finalisée.</p>
<p><strong>Récapitulatif :</strong></p>
<ul>
@foreach($order->items as $item)
    <li>{{ $item->quantity }} x {{ $item->product->name ?? 'Produit' }} — {{ number_format($item->unit_price * $item->quantity, 0, ',', ' ') }} FCFA</li>
@endforeach
</ul>
<p>Nous espérons que vous êtes satisfait de votre achat. N’hésitez pas à laisser un avis sur nos produits.</p>
<p>Merci d’avoir choisi GRO App.</p>
</body>
</html>
