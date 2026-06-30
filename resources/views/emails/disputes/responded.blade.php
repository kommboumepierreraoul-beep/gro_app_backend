<!DOCTYPE html>
<html>
<body style="font-family: sans-serif; background:#f4f4f4; padding:20px;">
<div style="max-width:600px;margin:auto;background:white;border-radius:12px;padding:30px;">
  <h2 style="color:#059669;">Réponse à votre litige</h2>
  <p>Le vendeur a répondu à votre litige sur la commande <strong>#{{ $dispute->order->order_number }}</strong>.</p>
  <p><strong>Sa réponse :</strong> {{ $dispute->seller_response }}</p>
  <a href="{{ config('app.url') }}/disputes/{{ $dispute->id }}"
     style="display:inline-block;margin-top:16px;padding:12px 24px;background:#059669;color:white;border-radius:8px;text-decoration:none;">
    Voir le litige
  </a>
</div>
</body>
</html>
