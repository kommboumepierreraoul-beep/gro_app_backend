<!DOCTYPE html>
<html>
<body style="font-family: sans-serif; background:#f4f4f4; padding:20px;">
<div style="max-width:600px;margin:auto;background:white;border-radius:12px;padding:30px;">
  <h2 style="color:#059669;">Litige résolu</h2>
  <p>Votre litige sur la commande <strong>#{{ $dispute->order->order_number }}</strong> a été résolu.</p>
  @if($dispute->admin_notes)
  <p><strong>Décision :</strong> {{ $dispute->admin_notes }}</p>
  @endif
  @if($dispute->refund_amount)
  <p style="color:#059669;font-size:18px;font-weight:bold;">
    Remboursement : {{ number_format($dispute->refund_amount, 0, ',', ' ') }} FCFA crédité sur votre portefeuille.
  </p>
  @endif
  <a href="{{ config('app.url') }}/disputes/{{ $dispute->id }}"
     style="display:inline-block;margin-top:16px;padding:12px 24px;background:#059669;color:white;border-radius:8px;text-decoration:none;">
    Voir le litige
  </a>
</div>
</body>
</html>
