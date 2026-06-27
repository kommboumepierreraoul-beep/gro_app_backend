'use client';

import { useState, useEffect } from 'react';
import { useParams, useRouter } from 'next/navigation';
import VendorLayout from '@/components/layouts/VendorLayout';
import api from '@/lib/axios';
import toast from 'react-hot-toast';
import { AlertCircle, Upload, X } from 'lucide-react';

export default function CreateDisputePage() {
  const { orderId } = useParams();
  const router = useRouter();
  const [order, setOrder] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [form, setForm] = useState({
    reason: 'not_received',
    description: '',
    attachments: [] as File[],
  });
  const [previews, setPreviews] = useState<string[]>([]);

  useEffect(() => {
    fetchOrder();
  }, []);

  const fetchOrder = async () => {
    try {
      const res = await api.get(`/orders/${orderId}`);
      setOrder(res.data.data);
    } catch (error) {
      toast.error('Commande introuvable');
      router.push('/orders');
    } finally {
      setLoading(false);
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(e.target.files || []);
    setForm({ ...form, attachments: [...form.attachments, ...files] });
    files.forEach(file => {
      const reader = new FileReader();
      reader.onloadend = () => {
        setPreviews(prev => [...prev, reader.result as string]);
      };
      reader.readAsDataURL(file);
    });
  };

  const removeFile = (index: number) => {
    setForm({
      ...form,
      attachments: form.attachments.filter((_, i) => i !== index),
    });
    setPreviews(previews.filter((_, i) => i !== index));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.description.trim()) {
      toast.error('Veuillez décrire votre problème');
      return;
    }
    setSubmitting(true);
    try {
      const formData = new FormData();
      formData.append('order_id', orderId as string);
      formData.append('reason', form.reason);
      formData.append('description', form.description);
      form.attachments.forEach(file => {
        formData.append('attachments[]', file);
      });
      await api.post('/disputes', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      toast.success('Litige créé avec succès');
      router.push('/disputes');
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Erreur lors de la création');
    } finally {
      setSubmitting(false);
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
      <div className="max-w-2xl mx-auto space-y-8">
        <div>
          <h1 className="text-2xl font-bold text-slate-800">Signaler un problème</h1>
          <p className="text-slate-500 mt-1">Commande #{order?.order_number}</p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">Type de problème</label>
            <select
              value={form.reason}
              onChange={(e) => setForm({ ...form, reason: e.target.value })}
              className="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-emerald-400 focus:ring-emerald-100"
            >
              <option value="not_received">Je n'ai pas reçu ma commande</option>
              <option value="damaged">Produit endommagé à la réception</option>
              <option value="wrong_product">Produit différent de celui commandé</option>
              <option value="other">Autre problème</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">Description détaillée</label>
            <textarea
              rows={5}
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
              placeholder="Expliquez précisément le problème..."
              className="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-emerald-400 focus:ring-emerald-100"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700 mb-2">Preuves (photos)</label>
            <div className="border-2 border-dashed border-slate-200 rounded-xl p-6 text-center hover:border-emerald-400 transition">
              <input
                type="file"
                accept="image/*"
                multiple
                onChange={handleFileChange}
                className="hidden"
                id="file-upload"
              />
              <label htmlFor="file-upload" className="cursor-pointer flex flex-col items-center gap-2">
                <Upload className="w-8 h-8 text-slate-400" />
                <span className="text-sm text-slate-500">Cliquez pour ajouter des photos</span>
              </label>
            </div>
            {previews.length > 0 && (
              <div className="mt-4 grid grid-cols-3 gap-3">
                {previews.map((preview, idx) => (
                  <div key={idx} className="relative group">
                    <img src={preview} alt="preview" className="w-full h-24 object-cover rounded-lg" />
                    <button
                      type="button"
                      onClick={() => removeFile(idx)}
                      className="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-0.5 opacity-0 group-hover:opacity-100 transition"
                    >
                      <X size={14} />
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>

          <button
            type="submit"
            disabled={submitting}
            className="w-full py-3 bg-emerald-600 text-white rounded-xl font-semibold hover:bg-emerald-700 transition disabled:opacity-50"
          >
            {submitting ? 'Envoi en cours...' : 'Envoyer ma réclamation'}
          </button>
        </form>
      </div>
    </VendorLayout>
  );
}
