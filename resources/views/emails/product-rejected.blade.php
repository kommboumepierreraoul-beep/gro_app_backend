<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Produit rejeté</title>
</head>
<body style="font-family:Arial;padding:20px;">
<h2>Bonjour {{ $user }}</h2>
<p>Votre produit <b>{{ $product }}</b> n'a pas été validé.</p>
<p>Motif du rejet :</p>
<div style="background:#eeeeee;padding:15px;border-radius:8px">
{{ $reason }}
</div>
<br>
Veuillez effectuer les corrections nécessaires puis soumettre à nouveau votre produit.
</body>
</html>