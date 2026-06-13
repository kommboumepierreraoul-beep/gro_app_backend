<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function show(Order $order)
    {
        $this->authorize('view', $order);
        $invoice = $order->invoice;
        if (!$invoice) return response()->json(['message' => 'Facture non générée'], 404);
        return response()->json([
            'invoice_number' => $invoice->invoice_number,
            'created_at' => $invoice->created_at,
            'downloaded' => !is_null($invoice->downloaded_at),
        ]);
    }

    public function generate(Order $order, Request $request)
    {
        $user = $request->user();
        $isClient = ($user->id === $order->user_id);
        $isSeller = ($user->id === $order->shop->user_id);
        $isAdmin = ($user->role === 'admin');

        if (!$isClient && !$isSeller && !$isAdmin) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!in_array($order->status, ['paid', 'preparing', 'shipping', 'delivered', 'completed'])) {
            return response()->json(['message' => 'Commande non payée'], 422);
        }

        if ($order->invoice) {
            return response()->json([
                'message' => 'Facture déjà générée',
                'download_url' => route('invoice.download', ['token' => $order->invoice->download_token]),
                'invoice_number' => $order->invoice->invoice_number,
            ]);
        }

        // Génération PDF
        $pdf = Pdf::loadView('invoices.invoice', ['order' => $order->load(['items.product', 'user'])]);
        $pdfContent = $pdf->output();
        $fileHash = hash('sha256', $pdfContent);
        $downloadToken = Str::random(64);
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . $order->id;

        $invoice = Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'file_hash' => $fileHash,
            'download_token' => $downloadToken,
        ]);

        Storage::disk('local')->put("invoices/{$invoice->id}.pdf", $pdfContent);

        return response()->json([
            'message' => 'Facture générée',
            'download_url' => route('invoice.download', ['token' => $downloadToken]),
            'invoice_number' => $invoiceNumber,
        ]);
    }

    public function download($token)
    {
        $invoice = Invoice::where('download_token', $token)->first();
        if (!$invoice) return response()->json(['message' => 'Lien invalide'], 404);

        $invoice->update([
            'ip_address' => request()->ip(),
            'downloaded_at' => now(),
        ]);

        $path = storage_path("app/invoices/{$invoice->id}.pdf");
        if (!file_exists($path)) return response()->json(['message' => 'Fichier introuvable'], 404);

        return response()->download($path, "facture_{$invoice->invoice_number}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
