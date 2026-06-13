<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Nouvelle commande reçue</title></head>
<body style="font-family: Arial, sans-serif;">
<h2>🛒 Nouvelle commande payée</h2>
<p>Bonjour {{ $order->shop->user->firstname ?? 'Vendeur' }},</p>
<p>Vous avez reçu une commande (n° <strong>{{ $order->order_number }}</strong>) d’un montant total de <strong>{{ number_format($order->total_amount, 0, ',', ' ') }} FCFA</strong>.</p>
<p><strong>Produits :</strong></p>
<ul>
@foreach($order->items as $item)
    <li>{{ $item->quantity }} x {{ $item->product->name ?? 'Produit' }} — {{ number_format($item->unit_price * $item->quantity, 0, ',', ' ') }} FCFA</li>
@endforeach
</ul>
<p>Adresse de livraison : {{ $order->shipping_address }}</p>
<p>Connectez-vous à votre espace vendeur pour préparer la commande.</p>
</body>
</html>
