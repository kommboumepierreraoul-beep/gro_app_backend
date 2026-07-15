<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ShopApprovedMail;
use App\Mail\ShopRejectedMail;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\Mission;
use App\Models\MissionApplication;
use App\Models\MissionCategory;
use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\ShopApprovedNotification;
use App\Notifications\ShopRejectedNotification;
use App\Services\BrevoMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminSystemController extends Controller
{
    private const RESOURCES = [
        'users',
        'shops',
        'products',
        'categories',
        'missions',
        'mission-categories',
        'mission-applications',
        'orders',
        'wallets',
        'transactions',
        'disputes',
    ];

    public function index(Request $request, string $resource)
    {
        $this->ensureResource($resource);

        $limit = min((int) $request->query('limit', 20), 100);
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $query = match ($resource) {
            'users' => User::query()->withCount(['posts', 'orders'])->with('shop:id,user_id,name,status'),
            'shops' => Shop::query()->with('user:id,firstname,lastname,email,status')->withCount('products'),
            'products' => Product::query()->with(['shop:id,name,status,user_id', 'category:id,name']),
            'categories' => Category::query(),
            'missions' => Mission::query()->with(['author:id,firstname,lastname,email,status', 'category:id,name'])->withCount('applications'),
            'mission-categories' => MissionCategory::query()->withCount('missions'),
            'mission-applications' => MissionApplication::query()->with([
                'mission:id,ulid,title,status',
                'applicant:id,firstname,lastname,email,status',
            ]),
            'orders' => Order::query()->with([
                'user:id,firstname,lastname,email,status',
                'seller:id,firstname,lastname,email,status',
                'shop:id,name,status',
                'items.product:id,name,price',
            ]),
            'wallets' => Wallet::query()->with('user:id,firstname,lastname,email,status'),
            'transactions' => WalletTransaction::query()->with('user:id,firstname,lastname,email,status'),
            'disputes' => Dispute::query()->with([
                'order:id,order_number,total_amount,status,payment_status',
                'user:id,firstname,lastname,email,status',
                'seller:id,firstname,lastname,email,status',
            ]),
        };

        $this->applySearch($query, $resource, $search);
        $this->applyStatus($query, $resource, $status);

        $results = $query->orderByDesc('created_at')->paginate($limit);

        return response()->json([
            'success' => true,
            'data' => $results->items(),
            'pagination' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, string $resource)
    {
        $this->ensureResource($resource);

        $model = match ($resource) {
            'users' => $this->createUser($request),
            'shops' => $this->createShop($request),
            'products' => $this->createProduct($request),
            'categories' => $this->createCategory($request),
            'missions' => $this->createMission($request),
            'mission-categories' => $this->createMissionCategory($request),
            default => abort(422, 'Creation non disponible pour cette ressource.'),
        };

        return response()->json(['success' => true, 'data' => $model], 201);
    }

    public function show(string $resource, int $id)
    {
        $model = $this->findResource($resource, $id);

        return response()->json(['success' => true, 'data' => $model]);
    }

    public function update(Request $request, string $resource, int $id)
    {
        $model = $this->findResource($resource, $id);

        match ($resource) {
            'users' => $this->updateUser($request, $model),
            'shops' => $this->updateModel($request, $model, ['name', 'description', 'address', 'city', 'phone', 'status']),
            'products' => $this->updateModel($request, $model, [
                'category_id', 'name', 'description', 'price', 'unit_price', 'weight',
                'stock', 'stock_quantity', 'status', 'approval_status', 'rejection_reason',
                'listing_type', 'delivery_condition', 'variety', 'origin', 'certification',
                'harvest_date', 'expiration_date', 'is_featured',
            ]),
            'categories' => $this->updateCategory($request, $model),
            'missions' => $this->updateModel($request, $model, [
                'category_id', 'title', 'description', 'desired_profile', 'duration_type',
                'duration_value', 'start_date', 'expires_at', 'location_label',
                'diffusion_radius_km', 'diffusion_scope', 'remuneration_type',
                'remuneration_amount', 'remuneration_currency', 'remuneration_conditions',
                'contact_methods', 'application_form', 'allow_attachments',
                'max_applications', 'status',
            ]),
            'mission-categories' => $this->updateModel($request, $model, ['name', 'slug', 'icon', 'color', 'sort_order', 'active']),
            'mission-applications' => $this->updateModel($request, $model, ['status', 'author_note', 'rejection_reason']),
            'orders' => $this->updateModel($request, $model, [
                'status', 'payment_status', 'payment_method', 'shipping_address',
                'payment_reference', 'client_confirmed_delivery', 'seller_confirmed_delivery',
            ]),
            'wallets' => $this->updateModel($request, $model, ['balance', 'currency']),
            'transactions' => $this->updateModel($request, $model, ['status', 'description', 'reference', 'metadata', 'completed_at']),
            'disputes' => $this->updateModel($request, $model, [
                'status', 'admin_notes', 'resolution', 'refund_amount', 'mode', 'admin_question',
            ]),
        };

        return response()->json(['success' => true, 'data' => $model->fresh()]);
    }

    public function destroy(string $resource, int $id)
    {
        $model = $this->findResource($resource, $id);
        $model->delete();

        return response()->json(['success' => true, 'message' => 'Ressource supprimee']);
    }

    public function action(Request $request, string $resource, int $id, string $action)
    {
        $model = $this->findResource($resource, $id);

        match ($resource) {
            'users' => $this->userAction($request, $model, $action),
            'shops' => $this->shopAction($request, $model, $action),
            'products' => $this->productAction($request, $model, $action),
            'missions' => $this->missionAction($model, $action),
            'mission-applications' => $this->missionApplicationAction($request, $model, $action),
            'orders' => $this->orderAction($model, $action),
            'transactions' => $this->transactionAction($model, $action),
            'disputes' => $this->disputeAction($request, $model, $action),
            default => abort(422, 'Action non disponible pour cette ressource.'),
        };

        return response()->json(['success' => true, 'data' => $model->fresh()]);
    }

    public function userInsights(int $id)
    {
        $user = User::with([
            'profile',
            'shop.products',
            'wallet',
        ])->withCount([
            'posts',
            'comments',
            'likes',
            'followers',
            'following',
            'orders',
            'reviews',
            'messages',
            'communityNotifications',
        ])->findOrFail($id);

        $shop = $user->shop;
        $sellerOrderQuery = Order::where('seller_id', $user->id);
        if ($shop) {
            $sellerOrderQuery->orWhere('shop_id', $shop->id);
        }

        $buyerOrders = Order::where('user_id', $user->id);
        $sellerOrders = Order::where(function ($query) use ($user, $shop) {
            $query->where('seller_id', $user->id);
            if ($shop) {
                $query->orWhere('shop_id', $shop->id);
            }
        });

        $products = Product::where('user_id', $user->id)
            ->when($shop, fn ($query) => $query->orWhere('shop_id', $shop->id));

        $wallet = $user->wallet;
        $walletTransactions = WalletTransaction::where('user_id', $user->id);

        $missionsAuthored = Mission::where('author_id', $user->id);
        $missionApplications = MissionApplication::where('applicant_id', $user->id);

        $disputesAsClient = Dispute::where('user_id', $user->id);
        $disputesAsSeller = Dispute::where('seller_id', $user->id);

        $monthly = collect(range(5, 0))->map(function (int $monthsAgo) use ($user, $shop) {
            $date = now()->subMonths($monthsAgo);
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();

            return [
                'month' => $date->locale('fr')->translatedFormat('M'),
                'community_posts' => Post::where('user_id', $user->id)->whereBetween('created_at', [$start, $end])->count(),
                'buyer_orders' => Order::where('user_id', $user->id)->whereBetween('created_at', [$start, $end])->count(),
                'seller_orders' => Order::where(function ($query) use ($user, $shop) {
                    $query->where('seller_id', $user->id);
                    if ($shop) {
                        $query->orWhere('shop_id', $shop->id);
                    }
                })->whereBetween('created_at', [$start, $end])->count(),
                'wallet_amount' => (float) WalletTransaction::where('user_id', $user->id)
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('amount'),
                'missions' => Mission::where('author_id', $user->id)->whereBetween('created_at', [$start, $end])->count(),
            ];
        })->values();

        $recentOrders = Order::with(['shop:id,name', 'items.product:id,name'])
            ->where(function ($query) use ($user, $shop) {
                $query->where('user_id', $user->id)->orWhere('seller_id', $user->id);
                if ($shop) {
                    $query->orWhere('shop_id', $shop->id);
                }
            })
            ->latest()
            ->limit(8)
            ->get();

        $recentPosts = Post::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(6)
            ->get(['id', 'title', 'content', 'type', 'likes_count', 'comments_count', 'shares_count', 'created_at']);

        $recentTransactions = WalletTransaction::where('user_id', $user->id)
            ->latest()
            ->limit(8)
            ->get(['id', 'type', 'amount', 'status', 'reference', 'description', 'created_at', 'completed_at']);

        $recentMissions = Mission::with('category:id,name')
            ->where('author_id', $user->id)
            ->latest()
            ->limit(6)
            ->get(['id', 'ulid', 'category_id', 'title', 'status', 'location_label', 'remuneration_amount', 'remuneration_currency', 'created_at']);

        $mapPoints = $recentOrders
            ->filter(fn ($order) => $order->delivery_latitude && $order->delivery_longitude)
            ->map(fn ($order) => [
                'id' => $order->id,
                'label' => $order->order_number,
                'lat' => (float) $order->delivery_latitude,
                'lng' => (float) $order->delivery_longitude,
                'status' => $order->status,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'summary' => [
                    'account_age_days' => $user->created_at ? Carbon::parse($user->created_at)->diffInDays(now()) : 0,
                    'community_score' => (int) ($user->posts_count * 4 + $user->comments_count * 2 + $user->likes_count + $user->followers_count * 3),
                    'marketplace_score' => (int) ($buyerOrders->count() * 2 + $sellerOrders->count() * 3 + $products->count() * 2),
                    'risk_flags' => [
                        'suspended' => ($user->status ?? 'active') !== 'active',
                        'publishing_blocked' => $user->publishing_blocked_until && Carbon::parse($user->publishing_blocked_until)->isFuture(),
                        'open_disputes' => (clone $disputesAsClient)->whereNotIn('status', ['resolved', 'closed'])->count()
                            + (clone $disputesAsSeller)->whereNotIn('status', ['resolved', 'closed'])->count(),
                        'pending_products' => (clone $products)->where('approval_status', 'pending')->count(),
                    ],
                ],
                'community' => [
                    'posts' => $user->posts_count,
                    'comments' => $user->comments_count,
                    'likes_given' => $user->likes_count,
                    'followers' => $user->followers_count,
                    'following' => $user->following_count,
                    'messages' => $user->messages_count,
                    'notifications' => $user->community_notifications_count,
                    'recent_posts' => $recentPosts,
                ],
                'marketplace' => [
                    'shop' => $shop,
                    'products_total' => (clone $products)->count(),
                    'products_approved' => (clone $products)->where('approval_status', 'approved')->count(),
                    'products_pending' => (clone $products)->where('approval_status', 'pending')->count(),
                    'products_rejected' => (clone $products)->where('approval_status', 'rejected')->count(),
                    'buyer_orders' => (clone $buyerOrders)->count(),
                    'seller_orders' => (clone $sellerOrders)->count(),
                    'buyer_spent' => (float) (clone $buyerOrders)->whereIn('status', ['paid', 'preparing', 'shipping', 'delivered', 'completed'])->sum('total_amount'),
                    'seller_revenue' => (float) (clone $sellerOrders)->whereIn('status', ['paid', 'preparing', 'shipping', 'delivered', 'completed'])->sum('total_amount'),
                    'recent_orders' => $recentOrders,
                    'map_points' => $mapPoints,
                ],
                'missions' => [
                    'authored_total' => (clone $missionsAuthored)->count(),
                    'authored_published' => (clone $missionsAuthored)->where('status', Mission::STATUS_PUBLISHED)->count(),
                    'applications_total' => (clone $missionApplications)->count(),
                    'applications_pending' => (clone $missionApplications)->where('status', MissionApplication::STATUS_PENDING)->count(),
                    'applications_accepted' => (clone $missionApplications)->where('status', MissionApplication::STATUS_ACCEPTED)->count(),
                    'recent_authored' => $recentMissions,
                    'recent_applications' => MissionApplication::with('mission:id,ulid,title,status')
                        ->where('applicant_id', $user->id)
                        ->latest()
                        ->limit(6)
                        ->get(),
                ],
                'finance' => [
                    'wallet' => $wallet,
                    'transactions_total' => (clone $walletTransactions)->count(),
                    'transactions_pending' => (clone $walletTransactions)->where('status', 'pending')->count(),
                    'transactions_completed' => (clone $walletTransactions)->where('status', 'completed')->count(),
                    'recent_transactions' => $recentTransactions,
                ],
                'disputes' => [
                    'as_client' => (clone $disputesAsClient)->count(),
                    'as_seller' => (clone $disputesAsSeller)->count(),
                    'open' => (clone $disputesAsClient)->whereNotIn('status', ['resolved', 'closed'])->count()
                        + (clone $disputesAsSeller)->whereNotIn('status', ['resolved', 'closed'])->count(),
                    'recent' => Dispute::with('order:id,order_number,total_amount,status')
                        ->where('user_id', $user->id)
                        ->orWhere('seller_id', $user->id)
                        ->latest()
                        ->limit(6)
                        ->get(),
                ],
                'charts' => [
                    'monthly' => $monthly,
                    'order_status' => Order::where(function ($query) use ($user, $shop) {
                        $query->where('user_id', $user->id)->orWhere('seller_id', $user->id);
                        if ($shop) {
                            $query->orWhere('shop_id', $shop->id);
                        }
                    })->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
                    'product_status' => (clone $products)->selectRaw('approval_status, COUNT(*) as total')->groupBy('approval_status')->pluck('total', 'approval_status'),
                ],
            ],
        ]);
    }

    private function createUser(Request $request): User
    {
        $data = $request->validate([
            'firstname' => ['required', 'string', 'max:120'],
            'lastname' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $data['password'] = Hash::make($data['password'] ?? Str::random(12));
        $data['status'] ??= 'active';

        return User::create($data);
    }

    private function createShop(Request $request): Shop
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);

        $data['slug'] = Str::slug($data['name']) . '-' . Str::lower(Str::random(5));
        $data['status'] ??= 'active';

        return Shop::create($data);
    }

    private function createProduct(Request $request): Product
    {
        $data = $request->validate([
            'shop_id' => ['required', 'exists:shops,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'max:40'],
            'approval_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ]);

        $shop = Shop::findOrFail($data['shop_id']);
        $data['user_id'] = $shop->user_id;
        $data['slug'] = Str::slug($data['name']) . '-' . Str::lower(Str::random(5));
        $data['status'] ??= 'active';
        $data['approval_status'] ??= 'approved';
        $data['images'] = [];

        return Product::create($data);
    }

    private function createCategory(Request $request): Category
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
        ]);
        $data['slug'] = Str::slug($data['name']);

        return Category::create($data);
    }

    private function createMission(Request $request): Mission
    {
        $data = $request->validate([
            'author_id' => ['required', 'exists:users,id'],
            'category_id' => ['nullable', 'exists:mission_categories,id'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string'],
            'desired_profile' => ['nullable', 'string'],
            'location_label' => ['nullable', 'string', 'max:180'],
            'remuneration_type' => ['nullable', 'string', 'max:40'],
            'remuneration_amount' => ['nullable', 'numeric', 'min:0'],
            'remuneration_currency' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'string', 'max:40'],
            'start_date' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $data['status'] ??= Mission::STATUS_PUBLISHED;
        $data['remuneration_currency'] ??= 'XAF';
        $data['duration_type'] ??= Mission::DURATION_FLEXIBLE;
        $data['remuneration_type'] ??= Mission::REMUNERATION_NEGOTIABLE;
        $data['diffusion_scope'] ??= Mission::SCOPE_PLATFORM;

        return Mission::create($data);
    }

    private function createMissionCategory(Request $request): MissionCategory
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:180'],
            'icon' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:40'],
            'sort_order' => ['nullable', 'integer'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['slug'] ??= Str::slug($data['name']);
        $data['active'] ??= true;

        return MissionCategory::create($data);
    }

    private function updateUser(Request $request, User $user): void
    {
        $data = $request->validate([
            'firstname' => ['sometimes', 'string', 'max:120'],
            'lastname' => ['sometimes', 'string', 'max:120'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['sometimes', 'string', 'max:40'],
            'status' => ['sometimes', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
    }

    private function updateCategory(Request $request, Category $category): void
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'max:180'],
        ]);
        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        $category->update($data);
    }

    private function updateModel(Request $request, $model, array $fields): void
    {
        $payload = collect($request->only($fields))
            ->filter(fn ($value) => $value !== null)
            ->all();

        $model->update($payload);
    }

    private function userAction(Request $request, User $user, string $action): void
    {
        match ($action) {
            'block', 'suspend' => $user->update(['status' => 'suspended']),
            'unblock', 'activate' => $user->update(['status' => 'active']),
            'make-admin' => $user->update(['role' => 'admin']),
            'make-seller' => $user->update(['role' => 'seller']),
            'make-user' => $user->update(['role' => 'user']),
            'block-publishing' => $user->update([
                'publishing_blocked_until' => now()->addDays((int) $request->input('days', 7)),
            ]),
            'unblock-publishing' => $user->update(['publishing_blocked_until' => null]),
            default => abort(422, 'Action utilisateur inconnue.'),
        };
    }

    private function shopAction(Request $request, Shop $shop, string $action): void
    {
        match ($action) {
            'approve', 'activate' => $this->approveShop($shop),
            'reject' => $this->rejectShop($request, $shop),
            'suspend' => $shop->update(['status' => 'suspended']),
            'disable' => $shop->update(['status' => 'inactive']),
            default => abort(422, 'Action boutique inconnue.'),
        };
    }

    private function approveShop(Shop $shop): void
    {
        $shop->loadMissing('user');
        $shop->update(['status' => 'active']);

        if ($shop->user && !$shop->user->isAdmin() && $shop->user->role === 'user') {
            $shop->user->update(['role' => 'seller']);
        }

        if ($shop->user) {
            $shop->user->notify(new ShopApprovedNotification($shop));
            $this->sendShopDecisionMail($shop->user, new ShopApprovedMail($shop->user, $shop));
        }
    }

    private function rejectShop(Request $request, Shop $shop): void
    {
        $reason = (string) $request->input('reason', 'Votre demande de boutique doit etre corrigee avant validation.');
        $shop->loadMissing('user');
        $shop->update(['status' => 'rejected']);

        if ($shop->user) {
            $shop->user->notify(new ShopRejectedNotification($shop, $reason));
            $this->sendShopDecisionMail($shop->user, new ShopRejectedMail($shop->user, $shop, $reason));
        }
    }

    private function sendShopDecisionMail(User $user, $mailable): void
    {
        if (!$user->email) {
            return;
        }

        try {
            app(BrevoMailService::class)->sendMailable(
                $user->email,
                trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? '')) ?: $user->email,
                $mailable
            );
        } catch (\Throwable $error) {
            Log::warning('Shop decision email failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $error->getMessage(),
            ]);
        }
    }

    private function productAction(Request $request, Product $product, string $action): void
    {
        match ($action) {
            'approve' => $product->update(['approval_status' => 'approved', 'status' => 'active', 'rejection_reason' => null]),
            'reject' => $product->update([
                'approval_status' => 'rejected',
                'status' => 'draft',
                'rejection_reason' => $request->input('reason', 'Produit rejete par l administration.'),
            ]),
            'archive' => $product->update(['status' => 'archived']),
            'feature' => $product->update(['is_featured' => true]),
            'unfeature' => $product->update(['is_featured' => false]),
            default => abort(422, 'Action produit inconnue.'),
        };
    }

    private function missionAction(Mission $mission, string $action): void
    {
        match ($action) {
            'publish' => $mission->update(['status' => Mission::STATUS_PUBLISHED]),
            'suspend' => $mission->update(['status' => Mission::STATUS_SUSPENDED]),
            'archive' => $mission->update(['status' => Mission::STATUS_ARCHIVED]),
            'cancel' => $mission->update(['status' => Mission::STATUS_CANCELLED]),
            'complete' => $mission->update(['status' => Mission::STATUS_COMPLETED, 'completed_at' => now()]),
            default => abort(422, 'Action mission inconnue.'),
        };
    }

    private function missionApplicationAction(Request $request, MissionApplication $application, string $action): void
    {
        match ($action) {
            'accept' => $application->accept(),
            'reject' => $application->reject($request->input('reason')),
            'confirm' => $application->update(['status' => MissionApplication::STATUS_CONFIRMED, 'confirmed_at' => now()]),
            'withdraw' => $application->withdraw(),
            default => abort(422, 'Action candidature inconnue.'),
        };
    }

    private function orderAction(Order $order, string $action): void
    {
        match ($action) {
            'mark-paid' => $order->update(['status' => 'paid', 'payment_status' => 'completed']),
            'prepare' => $order->update(['status' => 'preparing']),
            'ship' => $order->update(['status' => 'shipping']),
            'deliver' => $order->update(['status' => 'delivered']),
            'complete' => $order->update(['status' => 'completed', 'payment_status' => 'completed']),
            'cancel' => $order->update(['status' => 'cancelled']),
            default => abort(422, 'Action commande inconnue.'),
        };
    }

    private function transactionAction(WalletTransaction $transaction, string $action): void
    {
        match ($action) {
            'complete' => $transaction->update(['status' => 'completed', 'completed_at' => now()]),
            'cancel', 'reject' => $transaction->update(['status' => 'cancelled']),
            'pending' => $transaction->update(['status' => 'pending', 'completed_at' => null]),
            default => abort(422, 'Action transaction inconnue.'),
        };
    }

    private function disputeAction(Request $request, Dispute $dispute, string $action): void
    {
        match ($action) {
            'escalate' => $dispute->update(['status' => 'escalated', 'escalated_at' => now()]),
            'investigate' => $dispute->update(['status' => 'investigating']),
            'resolve' => $dispute->update([
                'status' => 'resolved',
                'resolved_by' => $request->user()?->id,
                'resolution' => $request->input('resolution', 'Resolution effectuee par l administration.'),
                'admin_notes' => $request->input('admin_notes'),
            ]),
            'close' => $dispute->update(['status' => 'closed']),
            default => abort(422, 'Action litige inconnue.'),
        };
    }

    private function statusAction($model, string $action, array $mapping): void
    {
        if (!array_key_exists($action, $mapping)) {
            abort(422, 'Action inconnue.');
        }

        $model->update(['status' => $mapping[$action]]);
    }

    private function findResource(string $resource, int $id)
    {
        $this->ensureResource($resource);

        return match ($resource) {
            'users' => User::with('shop')->findOrFail($id),
            'shops' => Shop::with('user')->findOrFail($id),
            'products' => Product::with(['shop', 'category'])->findOrFail($id),
            'categories' => Category::findOrFail($id),
            'missions' => Mission::with(['author', 'category', 'applications.applicant'])->findOrFail($id),
            'mission-categories' => MissionCategory::withCount('missions')->findOrFail($id),
            'mission-applications' => MissionApplication::with(['mission', 'applicant'])->findOrFail($id),
            'orders' => Order::with(['user', 'seller', 'shop', 'items.product'])->findOrFail($id),
            'wallets' => Wallet::with(['user', 'transactions'])->findOrFail($id),
            'transactions' => WalletTransaction::with('user')->findOrFail($id),
            'disputes' => Dispute::with(['order', 'user', 'seller'])->findOrFail($id),
        };
    }

    private function ensureResource(string $resource): void
    {
        if (!in_array($resource, self::RESOURCES, true)) {
            abort(404, 'Ressource admin inconnue.');
        }
    }

    private function applySearch($query, string $resource, string $search): void
    {
        if ($search === '') {
            return;
        }

        match ($resource) {
            'users' => $query->where(function ($q) use ($search) {
                $q->where('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }),
            'shops', 'products', 'categories', 'mission-categories' => $query->where('name', 'like', "%{$search}%"),
            'missions' => $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('location_label', 'like', "%{$search}%");
            }),
            'orders' => $query->where('order_number', 'like', "%{$search}%"),
            'transactions' => $query->where('reference', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"),
            'disputes' => $query->where('reason', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"),
            default => null,
        };
    }

    private function applyStatus($query, string $resource, mixed $status): void
    {
        if (!$status || $status === 'all') {
            return;
        }

        match ($resource) {
            'categories', 'wallets' => null,
            'mission-categories' => $query->where('active', in_array($status, ['active', 'true', '1'], true)),
            'products' => $query->where(function ($q) use ($status) {
                $q->where('status', $status)->orWhere('approval_status', $status);
            }),
            'orders' => $query->where(function ($q) use ($status) {
                $q->where('status', $status)->orWhere('payment_status', $status);
            }),
            default => $query->where('status', $status),
        };
    }
}
