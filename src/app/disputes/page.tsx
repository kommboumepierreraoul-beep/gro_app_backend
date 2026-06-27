'use client';

import { useState, useEffect } from 'react';
import VendorLayout from '@/components/layouts/VendorLayout';
import api from '@/lib/axios';
import toast from 'react-hot-toast';
import Link from 'next/link';
import { AlertCircle, Package, ChevronRight, Clock, CheckCircle, XCircle, MessageCircle } from 'lucide-react';

type Dispute = {
  id: number;
  order_id: number;
  reason: string;
  description: string;
  status: 'pending' | 'investigating' | 'resolved' | 'refunded' | 'replaced' | 'dismissed';
  created_at: string;
  order: { order_number: string; total_amount: number };
  seller: { name: string };
};

const statusConfig: Record<string, { label: string; color: string; icon: any }> = {
  pending: { label: 'En attente', color: 'bg-amber-100 text-amber-700', icon: Clock },
  investigating: { label: 'En investigation', color: 'bg-blue-100 text-blue-700', icon: MessageCircle },
  resolved: { label: 'Résolu', color: 'bg-green-100 text-green-700', icon: CheckCircle },
  refunded: { label: 'Remboursé', color: 'bg-emerald-100 text-emerald-700', icon: CheckCircle },
  replaced: { label: 'Produit renvoyé', color: 'bg-purple-100 text-purple-700', icon: Package },
  dismissed: { label: 'Rejeté', color: 'bg-red-100 text-red-700', icon: XCircle },
};

export default function ClientDisputesPage() {
  const [disputes, setDisputes] = useState<Dispute[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDisputes();
  }, []);

  const fetchDisputes = async () => {
    try {
      const res = await api.get('/disputes');
      setDisputes(res.data.data);
    } catch (error) {
      toast.error('Impossible de charger vos litiges');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <VendorLayout>
        <div className="flex items-center justify-center h-64">
          <div className="text-slate-400">Chargement...</div>
        </div>
      </VendorLayout>
    );
  }

  return (
    <VendorLayout>
      <div className="space-y-8">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h1 className="text-3xl font-bold text-slate-800">Mes litiges</h1>
            <p className="text-slate-500 mt-1">Suivez l'état de vos réclamations</p>
          </div>
        </div>

        {disputes.length === 0 ? (
          <div className="bg-white rounded-2xl p-12 text-center shadow-sm border border-slate-100">
            <AlertCircle className="w-12 h-12 text-slate-300 mx-auto mb-3" />
            <p className="text-slate-500">Vous n'avez aucun litige en cours</p>
          </div>
        ) : (
          <div className="space-y-4">
            {disputes.map((dispute) => {
              const StatusIcon = statusConfig[dispute.status]?.icon || AlertCircle;
              return (
                <Link href={`/disputes/${dispute.id}`} key={dispute.id}>
                  <div className="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 hover:shadow-md transition group">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div className="flex-1">
                        <div className="flex items-center gap-3 flex-wrap">
                          <span className="font-mono text-sm font-semibold text-slate-600">#{dispute.order.order_number}</span>
                          <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium ${statusConfig[dispute.status]?.color}`}>
                            <StatusIcon size={12} />
                            {statusConfig[dispute.status]?.label}
                          </span>
                        </div>
                        <p className="text-slate-700 mt-2 line-clamp-2">{dispute.description}</p>
                        <div className="flex items-center gap-4 mt-3 text-xs text-slate-400">
                          <span>Motif : {dispute.reason === 'not_received' ? 'Non reçu' : dispute.reason === 'damaged' ? 'Endommagé' : dispute.reason === 'wrong_product' ? 'Mauvais produit' : 'Autre'}</span>
                          <span>• {new Date(dispute.created_at).toLocaleDateString('fr-FR')}</span>
                        </div>
                      </div>
                      <div className="text-right">
                        <div className="font-bold text-emerald-600">{dispute.order.total_amount.toLocaleString()} FCFA</div>
                        <ChevronRight className="w-5 h-5 text-slate-300 group-hover:text-emerald-500 transition ml-auto mt-2" />
                      </div>
                    </div>
                  </div>
                </Link>
              );
            })}
          </div>
        )}

        <div className="bg-emerald-50 rounded-2xl p-5 text-center">
          <p className="text-slate-600 text-sm">Un problème sur une commande ?</p>
          <Link href="/orders" className="inline-block mt-2 text-emerald-700 font-semibold text-sm hover:underline">
            Voir mes commandes
          </Link>
        </div>
      </div>
    </VendorLayout>
  );
}
