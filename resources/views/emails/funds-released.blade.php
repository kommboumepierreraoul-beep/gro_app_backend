<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Fonds virés</title></head>
<body style="font-family: Arial, sans-serif;">
<h2>💰 Vente finalisée – Fonds virés</h2>
<p>Bonjour {{ $order->shop->user->firstname ?? 'Vendeur' }},</p>
<p>Le client a confirmé la réception de la commande n° <strong>{{ $order->order_number }}</strong>.</p>
<p><strong>Détail de la commande :</strong></p>
<ul>
@foreach($order->items as $item)
    <li>{{ $item->quantity }} x {{ $item->product->name ?? 'Produit' }} — {{ number_format($item->unit_price * $item->quantity, 0, ',', ' ') }} FCFA</li>
@endforeach
</ul>
<p>Montant crédité sur votre wallet : <strong>{{ number_format($order->total_amount, 0, ',', ' ') }} FCFA</strong>.</p>
<p>Consultez votre historique de transaction dans votre espace vendeur.</p>
</body>
</html>
