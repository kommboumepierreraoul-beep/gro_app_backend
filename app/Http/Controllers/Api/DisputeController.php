<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\DisputeCreatedMail;
use App\Mail\DisputeRespondedMail;
use App\Mail\DisputeResolvedMail;
use App\Mail\DisputeEscalatedMail;
use App\Mail\NewDisputeMessageMail;
<<<<<<< HEAD
use App\Notifications\DisputeCreatedNotification;
use App\Notifications\DisputeRespondedNotification;
use App\Notifications\DisputeEscalatedNotification;
use App\Notifications\DisputeResolvedNotification;
use App\Notifications\DisputeMessageNotification;
=======
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2

class DisputeController extends Controller
{
    // Client : liste de ses litiges
    public function index(Request $request)
    {
        $disputes = Dispute::where('user_id', $request->user()->id)
<<<<<<< HEAD
            ->with(['order.items.product', 'seller.profile'])
=======
            ->with(['order', 'seller'])
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json(['data' => $disputes]);
    }

    // Vendeur : liste des litiges reçus
    public function sellerDisputes(Request $request)
    {
        $disputes = Dispute::where('seller_id', $request->user()->id)
<<<<<<< HEAD
            ->with(['order.items.product', 'user.profile'])
=======
            ->with(['order', 'user'])
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json(['data' => $disputes]);
    }

    // Admin : tous les litiges
    public function adminDisputes(Request $request)
    {
        $user = $request->user();
<<<<<<< HEAD
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        $disputes = Dispute::with(['order.items.product', 'user', 'seller'])
=======
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        $disputes = Dispute::with(['order', 'user', 'seller'])
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json(['data' => $disputes]);
    }

    // Client crée un litige
    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'reason' => 'required|in:not_received,damaged,wrong_product,other',
            'description' => 'required|string|min:10',
            'attachments' => 'nullable|array',
        ]);

        $user = $request->user();
<<<<<<< HEAD
        $order = Order::with('shop')->where('id', $request->order_id)
=======
        $order = Order::where('id', $request->order_id)
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Commande non trouvée'], 404);
        }

<<<<<<< HEAD
        if (!in_array($order->status, ['shipping', 'delivered', 'paid', 'completed'])) {
=======
        if (!in_array($order->status, ['shipping', 'delivered', 'paid'])) {
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
            return response()->json(['message' => 'Litige possible uniquement pour les commandes en cours ou livrées'], 422);
        }

        $existing = Dispute::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'investigating', 'negotiation'])
            ->first();
        if ($existing) {
            return response()->json(['message' => 'Un litige est déjà en cours pour cette commande'], 422);
        }

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
<<<<<<< HEAD
            'seller_id' => $order->shop->user_id,
            'reason' => $request->reason,
            'description' => $request->description,
            
            'attachments' => (function() use ($request) {
                $urls = [];
                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        $path = $file->store('disputes', 'public');
                        $urls[] = '/storage/' . $path;
                    }
                }
                return $urls;
            })(),
=======
            'seller_id' => $order->seller_id,
            'reason' => $request->reason,
            'description' => $request->description,
            'attachments' => $request->attachments,
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
            'status' => 'pending',
            'mode' => 'amiable',
        ]);

        try {
            Mail::to($dispute->seller->email)->send(new DisputeCreatedMail($dispute));
<<<<<<< HEAD
            $dispute->seller->notify(new DisputeCreatedNotification($dispute));
=======
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
            Mail::to(config('mail.admin_address', 'admin@agriconnect.com'))->send(new DisputeCreatedMail($dispute));
        } catch (\Exception $e) {
            Log::error('Email litige échoué : ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'data' => $dispute]);
    }

    // Vendeur répond (message initial)
    public function respond(Request $request, Dispute $dispute)
    {
        $user = $request->user();
        if ($user->id !== $dispute->seller_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'response' => 'required|string|min:10',
            'attachments' => 'nullable|array',
        ]);

        $dispute->seller_response = $request->response;
        $sellerUrls = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('disputes', 'public');
                $sellerUrls[] = '/storage/' . $path;
            }
        }
        $dispute->seller_attachments = $sellerUrls;
        $dispute->status = 'negotiation';
        $dispute->save();

        try {
            Mail::to($dispute->user->email)->send(new DisputeRespondedMail($dispute));
<<<<<<< HEAD
            $dispute->user->notify(new DisputeRespondedNotification($dispute));
=======
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
            Mail::to(config('mail.admin_address', 'admin@agriconnect.com'))->send(new DisputeRespondedMail($dispute));
        } catch (\Exception $e) {
            Log::error('Email réponse litige échoué : ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'data' => $dispute]);
    }

    // Récupérer les messages d'un litige
    public function messages(Dispute $dispute)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }
<<<<<<< HEAD
        if ($user->id !== $dispute->user_id && $user->id !== $dispute->seller_id && !$user->isAdmin()) {
=======
        if ($user->id !== $dispute->user_id && $user->id !== $dispute->seller_id && $user->role !== 'admin') {
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $messages = DisputeMessage::where('dispute_id', $dispute->id)
<<<<<<< HEAD
            ->with('user.profile')
=======
            ->with('user')
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['data' => $messages]);
    }

    // Envoyer un message (chat)
    public function sendMessage(Request $request, Dispute $dispute)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }
<<<<<<< HEAD
        if ($user->id !== $dispute->user_id && $user->id !== $dispute->seller_id && !$user->isAdmin()) {
=======
        if ($user->id !== $dispute->user_id && $user->id !== $dispute->seller_id && $user->role !== 'admin') {
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'message' => 'required_without:attachments|string|nullable',
            'attachments' => 'nullable|array',
        ]);

        $message = DisputeMessage::create([
            'dispute_id' => $dispute->id,
            'user_id' => $user->id,
            'message' => $request->message,
<<<<<<< HEAD
            
            'attachments' => (function() use ($request) {
                $urls = [];
                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        $path = $file->store('disputes', 'public');
                        $urls[] = '/storage/' . $path;
                    }
                }
                return $urls;
            })(),
=======
            'attachments' => $request->attachments,
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
        ]);

        if ($dispute->status === 'pending') {
            $dispute->status = 'negotiation';
            $dispute->save();
        }

<<<<<<< HEAD
        if ($user->isAdmin()) {
            $recipients = [$dispute->user, $dispute->seller];
        } else {
            $recipients = [$user->id === $dispute->user_id ? $dispute->seller : $dispute->user];
        }
        try {
            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)->send(new NewDisputeMessageMail($dispute, $message));
                $senderName = $user->firstname . ' ' . $user->lastname;
                if (is_array($recipients)) {
                    foreach ($recipients as $r) { $r->notify(new DisputeMessageNotification($dispute, $senderName)); }
                } else {
                    $recipient->notify(new DisputeMessageNotification($dispute, $senderName));
                }
            }
=======
        $recipient = ($user->id === $dispute->user_id) ? $dispute->seller : $dispute->user;
        try {
            Mail::to($recipient->email)->queue(new NewDisputeMessageMail($dispute, $message));
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
        } catch (\Exception $e) {
            Log::error('Email nouveau message échoué : ' . $e->getMessage());
        }

        return response()->json(['data' => $message]);
    }

    // Escalader vers l'admin
    public function escalateToAdmin(Dispute $dispute)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }
        if ($user->id !== $dispute->user_id && $user->id !== $dispute->seller_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($dispute->mode !== 'amiable') {
            return response()->json(['message' => 'Litige déjà traité par l’admin'], 422);
        }

        $dispute->mode = 'admin';
        $dispute->status = 'escalated';
        $dispute->escalated_at = now();
        $dispute->save();

        try {
<<<<<<< HEAD
            $dispute->user->notify(new DisputeEscalatedNotification($dispute));
            $dispute->seller->notify(new DisputeEscalatedNotification($dispute));

            $admins = \App\Models\User::where('role', 'admin')->orWhere('is_admin', true)->get();
            foreach ($admins as $admin) {
                $admin->notify(new DisputeEscalatedNotification($dispute));
                Mail::to($admin->email)->send(new DisputeEscalatedMail($dispute));
            }
=======
            Mail::to(config('mail.admin_address', 'admin@agriconnect.com'))->send(new DisputeEscalatedMail($dispute));
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
        } catch (\Exception $e) {
            Log::error('Email escalade échoué : ' . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }

    // Clore le litige à l'amiable (accord entre client et vendeur)
    public function closeAmicably(Request $request, Dispute $dispute)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }
        if ($user->id !== $dispute->user_id && $user->id !== $dispute->seller_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate([
            'resolution' => 'required|in:refund,partial_refund,replace,dismissed',
            'refund_amount' => 'required_if:resolution,partial_refund|nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $dispute) {
            $dispute->resolution = $request->resolution;
            $dispute->status = 'resolved_amicably';
            if ($request->resolution === 'partial_refund') {
                $dispute->refund_amount = $request->refund_amount;
                $wallet = $dispute->user->wallet;
                if ($wallet) {
                    $wallet->credit($request->refund_amount, 'Remboursement partiel amiable commande ' . $dispute->order->order_number);
                }
            } elseif ($request->resolution === 'refund') {
                $dispute->refund_amount = $dispute->order->total_amount;
                $wallet = $dispute->user->wallet;
                if ($wallet) {
                    $wallet->credit($dispute->order->total_amount, 'Remboursement total amiable commande ' . $dispute->order->order_number);
                }
                $dispute->order->status = 'refunded';
                $dispute->order->save();
            }
            $dispute->save();
        });

        return response()->json(['success' => true]);
    }

    // Admin : poser une question au vendeur
    public function askSellerQuestion(Request $request, Dispute $dispute)
    {
        $user = auth()->user();
<<<<<<< HEAD
        if (!$user || !$user->isAdmin()) {
=======
        if (!$user || $user->role !== 'admin') {
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $request->validate(['question' => 'required|string']);

        $dispute->admin_question = $request->question;
        $dispute->save();

        try {
            Mail::to($dispute->seller->email)->send(new DisputeRespondedMail($dispute));
        } catch (\Exception $e) {
            Log::error('Email question admin échoué : ' . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }

    // Admin résout le litige (uniquement après escalade)
    public function resolve(Request $request, Dispute $dispute)
    {
        $user = $request->user();
<<<<<<< HEAD
        if (!$user->isAdmin()) {
=======
        if ($user->role !== 'admin') {
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($dispute->mode !== 'admin') {
            return response()->json(['message' => 'Ce litige n’a pas été escaladé. Résolvez-le d’abord à l’amiable.'], 422);
        }

        $request->validate([
            'resolution' => 'required|in:refund,partial_refund,replace,dismissed',
            'refund_amount' => 'required_if:resolution,partial_refund|nullable|numeric|min:0',
            'admin_notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $dispute, $user) {
            $dispute->resolution = $request->resolution;
            $dispute->admin_notes = $request->admin_notes;
            $dispute->resolved_by = $user->id;
            $dispute->status = 'resolved_by_admin';

            if ($request->resolution === 'refund') {
                $dispute->refund_amount = $dispute->order->total_amount;
                $wallet = $dispute->user->wallet;
                if ($wallet) {
                    $wallet->credit($dispute->order->total_amount, 'Remboursement litige commande ' . $dispute->order->order_number);
                }
                $dispute->order->status = 'refunded';
                $dispute->order->save();
            } elseif ($request->resolution === 'partial_refund') {
                $dispute->refund_amount = $request->refund_amount;
                $wallet = $dispute->user->wallet;
                if ($wallet) {
                    $wallet->credit($request->refund_amount, 'Remboursement partiel litige commande ' . $dispute->order->order_number);
                }
            } elseif ($request->resolution === 'replace') {
                $dispute->status = 'replaced';
            } else {
                $dispute->status = 'dismissed';
            }
            $dispute->save();

            try {
                Mail::to($dispute->user->email)->send(new DisputeResolvedMail($dispute));
<<<<<<< HEAD
                $dispute->user->notify(new DisputeResolvedNotification($dispute));
                Mail::to($dispute->seller->email)->send(new DisputeResolvedMail($dispute));
                $dispute->seller->notify(new DisputeResolvedNotification($dispute));
=======
                Mail::to($dispute->seller->email)->send(new DisputeResolvedMail($dispute));
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
            } catch (\Exception $e) {
                Log::error('Email résolution litige échoué : ' . $e->getMessage());
            }
        });

        return response()->json(['success' => true, 'data' => $dispute]);
    }

    // Récupérer un litige spécifique
    public function show(Dispute $dispute)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }
<<<<<<< HEAD
        if ($user->id !== $dispute->user_id && $user->id !== $dispute->seller_id && !$user->isAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        $dispute->load(['order.items.product', 'user.profile', 'seller.profile', 'resolver']);
=======
        if ($user->id !== $dispute->user_id && $user->id !== $dispute->seller_id && $user->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        $dispute->load(['order', 'user', 'seller', 'resolver']);
>>>>>>> 9a5ebbd473ed8da6d7209a514372a33f63ea9ac2
        return response()->json(['data' => $dispute]);
    }
}
