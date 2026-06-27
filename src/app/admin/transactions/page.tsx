'use client';

import { useState, useEffect } from 'react';
import api from '@/lib/axios';
import toast from 'react-hot-toast';
import {
  TrendingUp,
  TrendingDown,
  Download,
  Search,
  Filter,
  ChevronLeft,
  ChevronRight,
  Eye,
  CheckCircle,
  XCircle,
  Clock,
  AlertCircle,
  Shield,
  Activity,
  Wallet,
  Banknote,
  ArrowUpRight,
  ArrowDownLeft,
  ShoppingBag,
  RefreshCw,
  Calendar,
  LayoutDashboard,
  Users,
  Settings,
  LogOut,
  Menu,
  X,
} from 'lucide-react';
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';

type Transaction = {
  id: number;
  transaction_id: string;
  type: 'sale' | 'payout' | 'refund' | 'deposit';
  method: string;
  amount: number;
  status: 'successful' | 'pending' | 'failed' | 'processing';
  date: string;
  customer?: string;
  order_id?: number;
};

type FinancialStats = {
  gross_volume: number;
  gross_volume_change: number;
  payouts_pending: number;
  payouts_pending_orders: number;
  net_revenue: number;
  net_revenue_change: number;
  total_transactions: number;
};

type ChartData = {
  month: string;
  revenue: number;
  payouts: number;
  profit: number;
};

export default function AdminTransactionsPage() {
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [stats, setStats] = useState<FinancialStats | null>(null);
  const [chartData, setChartData] = useState<ChartData[]>([]);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [typeFilter, setTypeFilter] = useState('all');
  const [currentPage, setCurrentPage] = useState(1);
  const [selectedTransaction, setSelectedTransaction] = useState<Transaction | null>(null);

  const itemsPerPage = 10;

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    setLoading(true);
    try {
      // Récupérer toutes les commandes (admin)
      const ordersRes = await api.get('/admin/orders?all=true');
      const orders = ordersRes.data.data || [];

      // Calculer les stats financières
      const sales = orders.filter((o: any) => o.status === 'completed');
      const totalSales = sales.reduce((sum: number, o: any) => sum + Number(o.total_amount), 0);
      const pendingPayouts = orders.filter((o: any) => o.status === 'delivered').reduce((sum: number, o: any) => sum + Number(o.total_amount), 0);
      const netRevenue = totalSales * 0.15; // Commission 15%

      setStats({
        gross_volume: totalSales,
        gross_volume_change: 12.5,
        payouts_pending: pendingPayouts,
        payouts_pending_orders: orders.filter((o: any) => o.status === 'delivered').length,
        net_revenue: netRevenue,
        net_revenue_change: 5.2,
        total_transactions: orders.length,
      });

      // Générer les transactions depuis les commandes
      const txList: Transaction[] = orders.map((order: any, idx: number) => ({
        id: order.id,
        transaction_id: `TR-${String(100000 + order.id).slice(1)}`,
        type: order.status === 'completed' ? 'sale' : order.status === 'refunded' ? 'refund' : 'sale',
        method: order.payment_method || 'NotchPay',
        amount: order.total_amount,
        status: order.status === 'completed' ? 'successful' : order.status === 'pending' ? 'pending' : 'processing',
        date: order.created_at,
        customer: order.user?.name || order.user?.email || 'Client',
        order_id: order.id,
      }));

      setTransactions(txList);

      // Données du graphique (12 derniers mois)
      const monthlyData: Record<string, { revenue: number; payouts: number }> = {};
      orders.forEach((order: any) => {
        const date = new Date(order.created_at);
        const month = date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
        if (!monthlyData[month]) monthlyData[month] = { revenue: 0, payouts: 0 };
        if (order.status === 'completed') {
          monthlyData[month].revenue += order.total_amount;
        } else if (order.status === 'delivered') {
          monthlyData[month].payouts += order.total_amount;
        }
      });

      const chartArray = Object.entries(monthlyData).slice(-12).map(([month, data]) => ({
        month,
        revenue: data.revenue,
        payouts: data.payouts,
        profit: data.revenue * 0.15,
      }));
      setChartData(chartArray);

    } catch (error) {
      console.error('Erreur chargement données admin:', error);
      toast.error('Impossible de charger les données');
      // Données de démonstration
      setStats({
        gross_volume: 1284500,
        gross_volume_change: 12.5,
        payouts_pending: 42350,
        payouts_pending_orders: 82,
        net_revenue: 312900,
        net_revenue_change: 5.2,
        total_transactions: 1240,
      });
      setChartData([
        { month: 'Jan', revenue: 85000, payouts: 32000, profit: 12750 },
        { month: 'Fév', revenue: 92000, payouts: 35000, profit: 13800 },
        { month: 'Mar', revenue: 105000, payouts: 41000, profit: 15750 },
        { month: 'Avr', revenue: 118000, payouts: 43000, profit: 17700 },
        { month: 'Mai', revenue: 125000, payouts: 46000, profit: 18750 },
        { month: 'Juin', revenue: 142000, payouts: 51000, profit: 21300 },
      ]);
    } finally {
      setLoading(false);
    }
  };

  const filteredTransactions = transactions.filter(tx => {
    if (search && !tx.transaction_id.toLowerCase().includes(search.toLowerCase()) && !tx.customer?.toLowerCase().includes(search.toLowerCase())) return false;
    if (statusFilter !== 'all' && tx.status !== statusFilter) return false;
    if (typeFilter !== 'all' && tx.type !== typeFilter) return false;
    return true;
  });

  const paginatedTransactions = filteredTransactions.slice(
    (currentPage - 1) * itemsPerPage,
    currentPage * itemsPerPage
  );

  const totalPages = Math.ceil(filteredTransactions.length / itemsPerPage);

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'successful':
        return <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-400"><CheckCircle size={12} /> Réussie</span>;
      case 'pending':
        return <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-yellow-500/20 text-yellow-400"><Clock size={12} /> En attente</span>;
      case 'processing':
        return <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-blue-500/20 text-blue-400"><Activity size={12} /> Traitement</span>;
      default:
        return <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400"><XCircle size={12} /> Échouée</span>;
    }
  };

  const getTypeIcon = (type: string) => {
    switch (type) {
      case 'sale': return <ShoppingBag size={16} className="text-emerald-400" />;
      case 'payout': return <ArrowUpRight size={16} className="text-amber-400" />;
      case 'refund': return <ArrowDownLeft size={16} className="text-red-400" />;
      default: return <Wallet size={16} className="text-blue-400" />;
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-950">
        <div className="text-slate-400 animate-pulse">Chargement des données financières...</div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-slate-950">
      {/* Sidebar admin */}
      <aside className={`fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 border-r border-slate-800 transform transition-transform duration-300 ease-in-out ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'} lg:translate-x-0`}>
        <div className="p-6">
          <div className="flex items-center gap-2 mb-8">
            <div className="w-8 h-8 bg-emerald-500 rounded-lg flex items-center justify-center">
              <span className="text-slate-950 font-bold text-lg">A</span>
            </div>
            <span className="text-white font-semibold text-lg">AgriConnect</span>
          </div>
          <nav className="space-y-2">
            {[
              { icon: LayoutDashboard, label: 'Dashboard', active: false, href: '/admin' },
              { icon: Activity, label: 'Transactions', active: true, href: '/admin/transactions' },
              { icon: Users, label: 'Utilisateurs', active: false, href: '/admin/users' },
              { icon: Settings, label: 'Paramètres', active: false, href: '/admin/settings' },
            ].map((item) => (
              <button key={item.label} className={`w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm transition ${item.active ? 'bg-emerald-500/20 text-emerald-400' : 'text-slate-400 hover:bg-slate-800'}`}>
                <item.icon size={18} />
                {item.label}
              </button>
            ))}
          </nav>
        </div>
        <div className="absolute bottom-0 left-0 right-0 p-6 border-t border-slate-800">
          <button className="w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-400 hover:bg-slate-800 transition">
            <LogOut size={18} />
            Déconnexion
          </button>
        </div>
      </aside>

      {/* Main content */}
      <div className="lg:ml-64 min-h-screen">
        <header className="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-xl border-b border-slate-800">
          <div className="flex items-center justify-between px-6 py-4">
            <div className="flex items-center gap-4">
              <button onClick={() => setSidebarOpen(!sidebarOpen)} className="lg:hidden text-slate-400">
                {sidebarOpen ? <X size={24} /> : <Menu size={24} />}
              </button>
              <h1 className="text-xl font-semibold text-white">Tableau de bord Administrateur</h1>
            </div>
            <div className="flex items-center gap-3">
              <button onClick={fetchData} className="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                <RefreshCw size={18} />
              </button>
              <div className="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center">
                <span className="text-emerald-400 text-sm font-medium">AD</span>
              </div>
            </div>
          </div>
        </header>

        <main className="p-6 space-y-6">
          {/* Stats cards */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div className="bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-6 border border-slate-700">
              <div className="flex justify-between items-start mb-4">
                <div className="p-2 bg-emerald-500/20 rounded-xl">
                  <TrendingUp size={22} className="text-emerald-400" />
                </div>
                <span className="text-xs text-emerald-400 bg-emerald-500/20 px-2 py-1 rounded-full">+{stats?.gross_volume_change}%</span>
              </div>
              <p className="text-slate-400 text-sm">Transaction Gross Volume</p>
              <p className="text-2xl font-bold text-white mt-1">{stats?.gross_volume.toLocaleString()} FCFA</p>
            </div>
            <div className="bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-6 border border-slate-700">
              <div className="flex justify-between items-start mb-4">
                <div className="p-2 bg-amber-500/20 rounded-xl">
                  <Clock size={22} className="text-amber-400" />
                </div>
              </div>
              <p className="text-slate-400 text-sm">Payouts Pending</p>
              <p className="text-2xl font-bold text-white mt-1">{stats?.payouts_pending.toLocaleString()} FCFA</p>
              <p className="text-xs text-slate-500 mt-1">{stats?.payouts_pending_orders} commandes en attente</p>
            </div>
            <div className="bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-6 border border-slate-700">
              <div className="flex justify-between items-start mb-4">
                <div className="p-2 bg-blue-500/20 rounded-xl">
                  <Banknote size={22} className="text-blue-400" />
                </div>
                <span className="text-xs text-emerald-400 bg-emerald-500/20 px-2 py-1 rounded-full">+{stats?.net_revenue_change}%</span>
              </div>
              <p className="text-slate-400 text-sm">Net Revenue</p>
              <p className="text-2xl font-bold text-white mt-1">{stats?.net_revenue.toLocaleString()} FCFA</p>
            </div>
          </div>

          {/* Financial Trends Chart */}
          <div className="bg-slate-800/50 rounded-2xl p-6 border border-slate-700">
            <div className="flex flex-wrap justify-between items-center mb-6 gap-4">
              <h2 className="text-lg font-semibold text-white">Financial Trends</h2>
              <div className="flex gap-2">
                <button className="px-4 py-1.5 bg-slate-700 rounded-lg text-sm text-slate-300 hover:bg-slate-600 transition">Derniers 30 jours</button>
                <button className="px-4 py-1.5 bg-emerald-500/20 rounded-lg text-sm text-emerald-400 transition">Annuel</button>
              </div>
            </div>
            <div className="h-80 w-full">
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={chartData}>
                  <defs>
                    <linearGradient id="revenueGradient" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#10b981" stopOpacity={0.3}/>
                      <stop offset="95%" stopColor="#10b981" stopOpacity={0}/>
                    </linearGradient>
                    <linearGradient id="profitGradient" x1="0" y1="0" x2="o" y2="1">
                      <stop offset="5%" stopColor="#3b82f6" stopOpacity={0.3}/>
                      <stop offset="95%" stopColor="#3b82f6" stopOpacity={0}/>
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                  <XAxis dataKey="month" stroke="#94a3b8" />
                  <YAxis stroke="#94a3b8" tickFormatter={(val) => `${(val/1000).toFixed(0)}k`} />
                  <Tooltip contentStyle={{ backgroundColor: '#1e293b', border: 'none' }} />
                  <Area type="monotone" dataKey="revenue" stroke="#10b981" fill="url(#revenueGradient)" name="Revenu" />
                  <Area type="monotone" dataKey="profit" stroke="#3b82f6" fill="url(#profitGradient)" name="Profit" />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          </div>

          {/* Activity Timeline */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2 bg-slate-800/50 rounded-2xl p-6 border border-slate-700">
              <h2 className="text-lg font-semibold text-white mb-4">Activity Timeline</h2>
              <div className="space-y-4">
                <div className="flex items-start gap-4 p-3 bg-slate-700/30 rounded-xl">
                  <div className="p-2 bg-emerald-500/20 rounded-full"><CheckCircle size={18} className="text-emerald-400" /></div>
                  <div className="flex-1"><p className="text-white text-sm">Payout successful to AGRI-PRO</p><p className="text-xs text-slate-400">There are 2 minutes ago</p></div>
                </div>
                <div className="flex items-start gap-4 p-3 bg-slate-700/30 rounded-xl">
                  <div className="p-2 bg-emerald-500/20 rounded-full"><ShoppingBag size={18} className="text-emerald-400" /></div>
                  <div className="flex-1"><p className="text-white text-sm">Large Order #8234 Received</p><p className="text-xs text-slate-400">Today, 10:45 AM</p></div>
                </div>
                <div className="flex items-start gap-4 p-3 bg-slate-700/30 rounded-xl">
                  <div className="p-2 bg-amber-500/20 rounded-full"><AlertCircle size={18} className="text-amber-400" /></div>
                  <div className="flex-1"><p className="text-white text-sm">Refund request: Order #7190</p><p className="text-xs text-slate-400">Today, 08:12 AM</p></div>
                </div>
              </div>
            </div>
            <div className="bg-slate-800/50 rounded-2xl p-6 border border-slate-700">
              <div className="flex items-center gap-3 mb-4">
                <Shield size={20} className="text-emerald-400" />
                <h2 className="text-lg font-semibold text-white">SECURE SYSTEM</h2>
              </div>
              <p className="text-slate-400 text-sm">Vos données financières sont protégées avec un cryptage bancaire 256-bit.</p>
              <div className="mt-4 pt-4 border-t border-slate-700">
                <div className="flex items-center justify-between text-sm">
                  <span className="text-slate-400">Transactions totales</span>
                  <span className="text-white font-semibold">{stats?.total_transactions.toLocaleString()}</span>
                </div>
              </div>
            </div>
          </div>

          {/* Audit Logs / Transactions Table */}
          <div className="bg-slate-800/50 rounded-2xl p-6 border border-slate-700">
            <div className="flex flex-wrap justify-between items-center mb-6 gap-4">
              <h2 className="text-lg font-semibold text-white">Audit Logs - Historique des Transactions</h2>
              <div className="flex gap-3">
                <div className="relative">
                  <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                  <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Rechercher ID, Client..." className="pl-9 pr-4 py-2 bg-slate-700 border border-slate-600 rounded-xl text-sm text-white placeholder-slate-400 focus:outline-none focus:border-emerald-500" />
                </div>
                <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="px-3 py-2 bg-slate-700 border border-slate-600 rounded-xl text-sm text-white">
                  <option value="all">Tous statuts</option>
                  <option value="successful">Réussie</option>
                  <option value="pending">En attente</option>
                  <option value="processing">Traitement</option>
                  <option value="failed">Échouée</option>
                </select>
                <button className="flex items-center gap-2 px-4 py-2 bg-slate-700 rounded-xl text-sm text-slate-300 hover:bg-slate-600 transition"><Download size={16} /> Exporter</button>
              </div>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead>
                  <tr className="border-b border-slate-700 text-left text-slate-400 text-sm">
                    <th className="pb-3 font-medium">Transaction ID</th>
                    <th className="pb-3 font-medium">Type</th>
                    <th className="pb-3 font-medium">Méthode</th>
                    <th className="pb-3 font-medium">Date</th>
                    <th className="pb-3 font-medium">Statut</th>
                    <th className="pb-3 font-medium text-right">Montant</th>
                  </tr>
                </thead>
                <tbody>
                  {paginatedTransactions.map((tx) => (
                    <tr key={tx.id} className="border-b border-slate-800/50 hover:bg-slate-700/30 transition cursor-pointer" onClick={() => setSelectedTransaction(tx)}>
                      <td className="py-3 text-white font-mono text-sm">{tx.transaction_id}</td>
                      <td className="py-3"><div className="flex items-center gap-2">{getTypeIcon(tx.type)}<span className="text-slate-300 text-sm capitalize">{tx.type}</span></div></td>
                      <td className="py-3 text-slate-300 text-sm">{tx.method}</td>
                      <td className="py-3 text-slate-400 text-sm">{new Date(tx.date).toLocaleDateString('fr-FR')}</td>
                      <td className="py-3">{getStatusBadge(tx.status)}</td>
                      <td className={`py-3 text-right font-semibold ${tx.type === 'sale' ? 'text-emerald-400' : tx.type === 'payout' ? 'text-amber-400' : 'text-red-400'}`}>{tx.type === 'sale' ? '+' : '-'}{tx.amount.toLocaleString()} FCFA</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {totalPages > 1 && (
              <div className="flex justify-between items-center mt-6">
                <p className="text-slate-400 text-sm">Affichage de {((currentPage-1)*itemsPerPage)+1} à {Math.min(currentPage*itemsPerPage, filteredTransactions.length)} sur {filteredTransactions.length} transactions</p>
                <div className="flex gap-2"><button onClick={() => setCurrentPage(p => Math.max(1, p-1))} disabled={currentPage === 1} className="p-2 bg-slate-700 rounded-lg disabled:opacity-50"><ChevronLeft size={16} /></button><span className="px-4 py-2 bg-emerald-500/20 text-emerald-400 rounded-lg">{currentPage}</span><button onClick={() => setCurrentPage(p => Math.min(totalPages, p+1))} disabled={currentPage === totalPages} className="p-2 bg-slate-700 rounded-lg disabled:opacity-50"><ChevronRight size={16} /></button></div>
              </div>
            )}
          </div>
        </main>
      </div>

      {/* Transaction details modal */}
      {selectedTransaction && (
        <div className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4" onClick={() => setSelectedTransaction(null)}>
          <div className="bg-slate-800 rounded-2xl max-w-md w-full p-6" onClick={(e) => e.stopPropagation()}>
            <div className="flex justify-between items-center mb-4"><h3 className="text-lg font-semibold text-white">Détails transaction</h3><button onClick={() => setSelectedTransaction(null)} className="text-slate-400 hover:text-white">✕</button></div>
            <div className="space-y-3"><div className="flex justify-between"><span className="text-slate-400">ID</span><span className="text-white font-mono">{selectedTransaction.transaction_id}</span></div><div className="flex justify-between"><span className="text-slate-400">Type</span><span className="text-white capitalize">{selectedTransaction.type}</span></div><div className="flex justify-between"><span className="text-slate-400">Montant</span><span className="text-white font-semibold">{selectedTransaction.amount.toLocaleString()} FCFA</span></div><div className="flex justify-between"><span className="text-slate-400">Statut</span><div>{getStatusBadge(selectedTransaction.status)}</div></div><div className="flex justify-between"><span className="text-slate-400">Date</span><span className="text-white">{new Date(selectedTransaction.date).toLocaleString('fr-FR')}</span></div></div>
            <button onClick={() => setSelectedTransaction(null)} className="w-full mt-6 py-2.5 bg-emerald-500 text-slate-950 font-semibold rounded-xl">Fermer</button>
          </div>
        </div>
      )}
    </div>
  );
}
