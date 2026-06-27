<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Votre colis est en chemin</title></head>
<body style="font-family: Arial, sans-serif;">
<h2>🚚 Votre colis est expédié</h2>
<p>Bonjour {{ $order->user->firstname ?? 'Client' }},</p>
<p>La commande n° <strong>{{ $order->order_number }}</strong> est en cours de livraison à l’adresse suivante :</p>
<p>{{ $order->shipping_address }}</p>
<p><strong>Produits :</strong></p>
<ul>
@foreach($order->items as $item)
    <li>{{ $item->quantity }} x {{ $item->product->name ?? 'Produit' }}</li>
@endforeach
</ul>
<p>Dès réception, pensez à confirmer la livraison depuis votre espace client.</p>
</body>
</html>
