'use client';

import { useState, useRef } from 'react';
import { useRouter } from 'next/navigation';
import api from '@/lib/axios';
import { 
  ArrowLeft, Store, Palette, Image, MapPin, Phone, Globe, 
  User, ShieldCheck, Loader2, Sparkles, Heart, Upload, X, AlertCircle, Building2, Navigation
} from 'lucide-react';

export default function ConfigureShopPage() {
  const router = useRouter();
  const [form, setForm] = useState({
    name: '',
    slug: '',
    description: '',
    address: '',
    city: '',
    phone: '',
  });
  const [logoPreview, setLogoPreview] = useState<string | null>(null);
  const [bannerPreview, setBannerPreview] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [focusedField, setFocusedField] = useState<string | null>(null);

  const logoFileRef = useRef<File | null>(null);
  const bannerFileRef = useRef<File | null>(null);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setForm({ ...form, [e.target.id]: e.target.value });
  };

  const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      logoFileRef.current = file;
      const reader = new FileReader();
      reader.onloadend = () => setLogoPreview(reader.result as string);
      reader.readAsDataURL(file);
    }
  };

  const handleBannerChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      bannerFileRef.current = file;
      const reader = new FileReader();
      reader.onloadend = () => setBannerPreview(reader.result as string);
      reader.readAsDataURL(file);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setError(null);

    try {
      const formData = new FormData();
      formData.append('name', form.name);
      formData.append('slug', form.slug);
      formData.append('description', form.description);
      formData.append('address', form.address);
      formData.append('city', form.city);
      formData.append('phone', form.phone);
      if (logoFileRef.current) formData.append('logo', logoFileRef.current);
      if (bannerFileRef.current) formData.append('banner', bannerFileRef.current);

      await api.post('/marketplace/shops', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      router.push('/my-shop');
    } catch (err: any) {
      const msg = err?.response?.data?.message || 'Erreur lors de la création de la boutique.';
      setError(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  const generateSlug = () => {
    if (form.name) {
      const slug = form.name
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
      setForm(prev => ({ ...prev, slug }));
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-900 to-emerald-900">
      <nav className="sticky top-0 z-50 bg-slate-900/80 backdrop-blur-xl border-b border-emerald-800/50 shadow-sm">
        <div className="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <button 
              onClick={() => router.back()} 
              className="p-2 rounded-full hover:bg-emerald-800/50 transition-all duration-200 text-emerald-300"
            >
              <ArrowLeft size={20} />
            </button>
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center shadow-lg shadow-emerald-500/20">
                <Store size={20} className="text-white" />
              </div>
              <span className="font-bold text-xl text-white">
                AgriConnect
              </span>
            </div>
          </div>
          <div className="flex items-center gap-4">
            <div className="hidden md:flex items-center gap-2 text-sm text-emerald-300 bg-emerald-800/30 px-4 py-2 rounded-full backdrop-blur-sm">
              <ShieldCheck size={16} />
              <span>Plateforme certifiée</span>
            </div>
            <div className="w-8 h-8 rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 flex items-center justify-center shadow-md">
              <User size={16} className="text-white" />
            </div>
          </div>
        </div>
      </nav>

      <main className="relative py-12 md:py-16 px-4">
        <div className="absolute inset-0 overflow-hidden pointer-events-none">
          <div className="absolute -top-40 -right-40 w-80 h-80 bg-emerald-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse" />
          <div className="absolute -bottom-40 -left-40 w-80 h-80 bg-teal-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse delay-1000" />
        </div>

        <div className="max-w-4xl mx-auto relative z-10">
          <div className="text-center mb-12">
            <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-emerald-800/50 text-emerald-300 text-sm font-medium mb-6 shadow-sm backdrop-blur-sm">
              <Sparkles size={16} />
              <span>Lancez votre boutique en ligne</span>
            </div>
            <h1 className="text-4xl md:text-5xl font-bold text-white mb-4">
              Créez votre vitrine
            </h1>
            <p className="text-slate-300 text-lg max-w-2xl mx-auto">
              Rejoignez des milliers d'agriculteurs et vendez vos produits à travers le Cameroun
            </p>
          </div>

          {error && (
            <div className="mb-8 p-4 bg-red-900/50 border border-red-700 rounded-2xl text-red-200 text-sm flex items-start gap-3 backdrop-blur-sm">
              <AlertCircle size={18} className="mt-0.5 flex-shrink-0" />
              <span>{error}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-8">
            {/* Identité visuelle */}
            <div className="bg-slate-800/70 backdrop-blur-sm rounded-2xl shadow-xl border border-slate-700 p-6 md:p-8">
              <div className="flex items-center gap-3 mb-6 pb-3 border-b border-slate-700">
                <div className="p-2 bg-slate-700 rounded-xl">
                  <Palette size={22} className="text-emerald-400" />
                </div>
                <div>
                  <h2 className="text-xl font-bold text-white">Identité visuelle</h2>
                  <p className="text-sm text-slate-400">Donnez vie à votre marque</p>
                </div>
              </div>

              <div className="grid md:grid-cols-2 gap-8">
                <div className="space-y-3">
                  <label className="block text-sm font-semibold text-slate-300">Logo de la boutique</label>
                  <div className={`relative group cursor-pointer aspect-square max-w-[200px] mx-auto rounded-2xl bg-slate-900/50 border-2 border-dashed transition-all ${logoPreview ? 'border-emerald-500' : 'border-slate-600 hover:border-emerald-500'}`}>
                    {logoPreview ? (
                      <div className="relative w-full h-full">
                        <img src={logoPreview} alt="Logo preview" className="w-full h-full rounded-2xl object-cover" />
                        <button type="button" onClick={() => { setLogoPreview(null); logoFileRef.current = null; }} className="absolute top-2 right-2 p-1 bg-red-500 rounded-full text-white hover:bg-red-600 transition">
                          <X size={14} />
                        </button>
                      </div>
                    ) : (
                      <div className="flex flex-col items-center justify-center h-full p-6 text-slate-400 group-hover:text-emerald-400 transition">
                        <Upload size={32} className="mb-2" />
                        <span className="text-xs font-medium">Cliquez ou glissez</span>
                        <span className="text-[10px]">PNG, JPG jusqu'à 2MB</span>
                      </div>
                    )}
                    <input type="file" accept="image/*" onChange={handleLogoChange} className="absolute inset-0 opacity-0 cursor-pointer" />
                  </div>
                </div>

                <div className="space-y-3">
                  <label className="block text-sm font-semibold text-slate-300">Bannière de couverture</label>
                  <div className={`relative group cursor-pointer aspect-[21/9] rounded-xl bg-slate-900/50 border-2 border-dashed transition-all ${bannerPreview ? 'border-emerald-500' : 'border-slate-600 hover:border-emerald-500'}`}>
                    {bannerPreview ? (
                      <div className="relative w-full h-full">
                        <img src={bannerPreview} alt="Banner preview" className="w-full h-full rounded-xl object-cover" />
                        <button type="button" onClick={() => { setBannerPreview(null); bannerFileRef.current = null; }} className="absolute top-2 right-2 p-1 bg-red-500 rounded-full text-white hover:bg-red-600 transition">
                          <X size={14} />
                        </button>
                      </div>
                    ) : (
                      <div className="flex flex-col items-center justify-center h-full p-4 text-slate-400 group-hover:text-emerald-400 transition">
                        <Image size={28} className="mb-2" />
                        <span className="text-xs font-medium">Ajouter une bannière</span>
                        <span className="text-[10px]">Recommandé 1200x400px</span>
                      </div>
                    )}
                    <input type="file" accept="image/*" onChange={handleBannerChange} className="absolute inset-0 opacity-0 cursor-pointer" />
                  </div>
                </div>
              </div>
            </div>

            {/* Informations générales */}
            <div className="bg-slate-800/70 backdrop-blur-sm rounded-2xl shadow-xl border border-slate-700 p-6 md:p-8">
              <div className="flex items-center gap-3 mb-6 pb-3 border-b border-slate-700">
                <div className="p-2 bg-slate-700 rounded-xl">
                  <Building2 size={22} className="text-emerald-400" />
                </div>
                <div>
                  <h2 className="text-xl font-bold text-white">Informations générales</h2>
                  <p className="text-sm text-slate-400">Détails de votre activité agricole</p>
                </div>
              </div>

              <div className="space-y-6">
                <div>
                  <label htmlFor="name" className="block text-sm font-semibold text-slate-300 mb-2">
                    Nom de la boutique <span className="text-red-400">*</span>
                  </label>
                  <div className="relative">
                    <Store size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                      type="text"
                      id="name"
                      value={form.name}
                      onChange={handleInputChange}
                      onFocus={() => setFocusedField('name')}
                      onBlur={() => setFocusedField(null)}
                      placeholder="Ex: Les Vergers d'Occitanie"
                      className={`w-full pl-11 pr-4 py-3.5 rounded-xl border-2 transition-all duration-200 bg-slate-900/50 text-white placeholder-slate-500 ${
                        focusedField === 'name' ? 'border-emerald-500 ring-4 ring-emerald-500/20' : 'border-slate-600'
                      } focus:outline-none`}
                      required
                    />
                  </div>
                </div>

                <div>
                  <label htmlFor="slug" className="block text-sm font-semibold text-slate-300 mb-2">
                    Adresse URL <span className="text-red-400">*</span>
                  </label>
                  <div className="relative">
                    <Globe size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                      type="text"
                      id="slug"
                      value={form.slug}
                      onChange={handleInputChange}
                      onFocus={() => setFocusedField('slug')}
                      onBlur={() => setFocusedField(null)}
                      placeholder="nom-boutique"
                      className={`w-full pl-11 pr-4 py-3.5 rounded-xl border-2 transition-all duration-200 bg-slate-900/50 text-white placeholder-slate-500 font-mono text-sm ${
                        focusedField === 'slug' ? 'border-emerald-500 ring-4 ring-emerald-500/20' : 'border-slate-600'
                      } focus:outline-none`}
                      required
                    />
                  </div>
                  <div className="flex justify-between items-center mt-2">
                    <p className="text-xs text-slate-400">agriconnect.com/{form.slug || 'votre-boutique'}</p>
                    {form.name && !form.slug && (
                      <button type="button" onClick={generateSlug} className="text-xs text-emerald-400 hover:text-emerald-300 font-medium">
                        Générer automatiquement
                      </button>
                    )}
                  </div>
                </div>

                <div>
                  <label htmlFor="description" className="block text-sm font-semibold text-slate-300 mb-2">
                    Description <span className="text-red-400">*</span>
                  </label>
                  <textarea
                    id="description"
                    rows={5}
                    value={form.description}
                    onChange={handleInputChange}
                    placeholder="Présentez votre exploitation, vos méthodes de culture, vos valeurs..."
                    className="w-full px-4 py-3.5 rounded-xl border-2 border-slate-600 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition-all duration-200 bg-slate-900/50 text-white placeholder-slate-500 resize-y"
                    required
                  />
                </div>
              </div>
            </div>

            {/* Coordonnées */}
            <div className="bg-slate-800/70 backdrop-blur-sm rounded-2xl shadow-xl border border-slate-700 p-6 md:p-8">
              <div className="flex items-center gap-3 mb-6 pb-3 border-b border-slate-700">
                <div className="p-2 bg-slate-700 rounded-xl">
                  <MapPin size={22} className="text-emerald-400" />
                </div>
                <div>
                  <h2 className="text-xl font-bold text-white">Coordonnées</h2>
                  <p className="text-sm text-slate-400">Où et comment vous joindre</p>
                </div>
              </div>

              <div className="space-y-6">
                <div>
                  <label htmlFor="address" className="block text-sm font-semibold text-slate-300 mb-2">
                    Adresse <span className="text-red-400">*</span>
                  </label>
                  <div className="relative">
                    <Navigation size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                      type="text"
                      id="address"
                      value={form.address}
                      onChange={handleInputChange}
                      placeholder="123 Route des Plaines"
                      className="w-full pl-11 pr-4 py-3.5 rounded-xl border-2 border-slate-600 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 bg-slate-900/50 text-white placeholder-slate-500"
                      required
                    />
                  </div>
                </div>

                <div className="grid md:grid-cols-2 gap-6">
                  <div>
                    <label htmlFor="city" className="block text-sm font-semibold text-slate-300 mb-2">
                      Ville <span className="text-red-400">*</span>
                    </label>
                    <input
                      type="text"
                      id="city"
                      value={form.city}
                      onChange={handleInputChange}
                      placeholder="Douala"
                      className="w-full px-4 py-3.5 rounded-xl border-2 border-slate-600 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 bg-slate-900/50 text-white placeholder-slate-500"
                      required
                    />
                  </div>
                  <div>
                    <label htmlFor="phone" className="block text-sm font-semibold text-slate-300 mb-2">
                      Téléphone <span className="text-red-400">*</span>
                    </label>
                    <div className="relative">
                      <Phone size={18} className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" />
                      <input
                        type="tel"
                        id="phone"
                        value={form.phone}
                        onChange={handleInputChange}
                        placeholder="6 12 34 56 78"
                        className="w-full pl-11 pr-4 py-3.5 rounded-xl border-2 border-slate-600 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 bg-slate-900/50 text-white placeholder-slate-500"
                        required
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Sécurité et validation */}
            <div className="bg-slate-800/50 backdrop-blur-sm rounded-2xl p-6 border border-slate-700">
              <div className="flex flex-wrap gap-6 justify-between items-center">
                <div className="flex items-center gap-3">
                  <div className="p-2 bg-slate-700 rounded-full">
                    <ShieldCheck size={20} className="text-emerald-400" />
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-white">Validation en 24h</p>
                    <p className="text-xs text-slate-400">Votre boutique sera vérifiée par notre équipe</p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <div className="p-2 bg-slate-700 rounded-full">
                    <Heart size={20} className="text-emerald-400" />
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-white">Communauté solidaire</p>
                    <p className="text-xs text-slate-400">Rejoignez des milliers d'agriculteurs</p>
                  </div>
                </div>
              </div>
            </div>

            {/* Bouton de soumission */}
            <button
              type="submit"
              disabled={isSubmitting}
              className="w-full py-4 px-6 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-bold text-lg shadow-lg shadow-emerald-500/30 hover:shadow-xl hover:shadow-emerald-500/40 hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 flex items-center justify-center gap-3 group disabled:opacity-70 disabled:cursor-not-allowed"
            >
              {isSubmitting ? (
                <>
                  <Loader2 size={22} className="animate-spin" />
                  Création en cours...
                </>
              ) : (
                <>
                  <Sparkles size={20} />
                  Lancer ma boutique
                  <ArrowLeft size={18} className="rotate-180 group-hover:translate-x-1 transition-transform" />
                </>
              )}
            </button>

            <p className="text-center text-xs text-slate-400 mt-6">
              En créant votre boutique, vous acceptez nos{' '}
              <a href="#" className="text-emerald-400 hover:underline font-medium">Conditions d'utilisation</a>
            </p>
          </form>

          <footer className="mt-16 pt-8 border-t border-slate-800 text-center">
            <p className="text-slate-500 text-sm">© 2024 AgriConnect. Tous droits réservés.</p>
          </footer>
        </div>
      </main>
    </div>
  );
}
