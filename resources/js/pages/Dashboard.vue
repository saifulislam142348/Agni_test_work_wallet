<template>
    <div class="min-h-screen bg-gray-50 font-sans">
        <nav class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <h1 class="text-2xl font-bold text-pink-600">WalletApp</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button @click="toggleLocale" class="text-gray-600 hover:text-gray-900 font-medium">
                            {{ locale === 'en' ? 'বাংলা' : 'English' }}
                        </button>
                        <button @click="logout" class="text-red-500 hover:text-red-700">
                            {{ t.logout }}
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
            
            <!-- Balance Card -->
            <div class="bg-gradient-to-r from-pink-500 to-rose-500 rounded-lg shadow-lg p-6 text-white mb-8">
                <h2 class="text-lg opacity-90">{{ t.balance }}</h2>
                <div class="text-4xl font-bold mt-2">
                    ৳ {{ balance }} <span class="text-lg font-normal">{{ currency }}</span>
                </div>
                
                <div class="mt-6 flex space-x-4">
                    <button @click="showAddMoneyModal = true" 
                        class="bg-white text-pink-600 px-6 py-2 rounded font-bold shadow hover:bg-gray-100 transition">
                        {{ t.add_money }}
                    </button>
                    
                    <button v-if="!hasAgreement" @click="linkWallet" 
                        class="bg-transparent border border-white text-white px-6 py-2 rounded font-bold hover:bg-white hover:text-pink-600 transition">
                        {{ t.link_wallet }}
                    </button>
                    <div v-else class="flex items-center bg-pink-700 bg-opacity-30 px-4 py-2 rounded">
                        <span class="text-sm">✔ {{ t.linked }}: {{ agreementPhone }}</span>
                    </div>
                </div>
            </div>

            <!-- Transaction History -->
            <TransactionHistory :locale="locale" />

        </main>

        <!-- Add Money Modal -->
        <div v-if="showAddMoneyModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 transition-opacity duration-300">
            <div class="bg-white rounded-lg p-8 w-96 shadow-xl transform transition-all scale-100">
                <h3 class="text-lg font-bold mb-4">{{ t.add_money }}</h3>
                
                <div class="mb-4">
                    <label class="block text-sm font-bold mb-2">Select Payment Method</label>
                    
                    <div v-for="agm in agreements" :key="agm.id" 
                        class="flex items-center justify-between p-3 border rounded mb-2 hover:bg-gray-50 cursor-pointer"
                        @click="selectedAgreementId = agm.id">
                        <div class="flex items-center">
                             <input type="radio" v-model="selectedAgreementId" :value="agm.id" class="mr-3 text-pink-600 focus:ring-pink-500" />
                             <span class="font-medium text-gray-700">bKash ({{ agm.payer_reference }})</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between p-3 border rounded hover:bg-gray-50 cursor-pointer"
                        @click="selectedAgreementId = 'new'">
                        <div class="flex items-center">
                             <input type="radio" v-model="selectedAgreementId" value="new" class="mr-3 text-pink-600 focus:ring-pink-500" />
                             <span class="font-medium text-gray-700">Add New Number</span>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <input 
                        v-model="amount" 
                        type="number" 
                        placeholder="Amount (BDT)" 
                        class="w-full border p-2 rounded focus:ring-2 focus:ring-pink-500 outline-none transition"
                        :class="{'border-red-500': errors.amount}"
                    />
                    <p v-if="errors.amount" class="text-red-500 text-sm mt-1">{{ errors.amount }}</p>
                </div>

                <div class="flex justify-end space-x-2">
                    <button @click="showAddMoneyModal = false" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded transition">Cancel</button>
                    <button @click="addMoney" :disabled="loading" class="px-4 py-2 bg-pink-600 text-white rounded hover:bg-pink-700 shadow transition flex items-center">
                        <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg> Go To
                        {{ loading ? t.processing : t.proceed_payment }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div v-if="flash.show" class="fixed top-20 right-5 z-50">
            <div :class="{
                'bg-green-500': flash.type === 'success',
                'bg-red-500': flash.type === 'error'
            }" class="text-white px-6 py-4 rounded shadow-lg flex items-center space-x-2 animate-bounce-in">
                <span class="font-bold text-lg" v-if="flash.type === 'success'">✓</span>
                <span class="font-bold text-lg" v-if="flash.type === 'error'">✕</span>
                <span>{{ flash.message }}</span>
                <button @click="flash.show = false" class="ml-4 opacity-75 hover:opacity-100 font-bold">×</button>
            </div>
        </div>

    </div>
</template>

<script>
import TransactionHistory from '../components/TransactionHistory.vue';
import { translations } from '../lang';

export default {
    components: { TransactionHistory },
    data() {
        return {
            locale: localStorage.getItem('locale') || 'en',
            balance: '0.00',
            currency: 'BDT',
            agreements: [],
            selectedAgreementId: 'new', // Default to new if empty, or specific if exists
            
            showAddMoneyModal: false,
            amount: '',
            loading: false,
            // Flash Message State
            flash: {
                show: false,
                message: '',
                type: 'success' // success, error
            },
            // Validation Errors
            errors: {
                amount: ''
            }
        }
    },
    computed: {
        t() {
            return translations[this.locale] || translations.en;
        }
    },
    mounted() {
        this.fetchDashboard();
        this.handleUrlParams();
    },
    methods: {
        handleUrlParams() {
            const urlParams = new URLSearchParams(window.location.search);
            const url = new URL(window.location);
            let dirty = false;

            // 1. Agreement Status
            if (urlParams.get('agreement_status') === 'success') {
                this.showAddMoneyModal = true;
                this.notify(this.t.success, 'success'); // "Success" or specific text
                url.searchParams.delete('agreement_status');
                dirty = true;
            } else if (urlParams.get('agreement_status') === 'failed') {
                this.notify('Agreement Failed: ' + (urlParams.get('error') || 'Unknown'), 'error');
                url.searchParams.delete('agreement_status');
                url.searchParams.delete('error');
                dirty = true;
            }

            // 2. Payment Status
            if (urlParams.get('payment_status') === 'success') {
                this.notify('Payment Successful! Balance Updated.', 'success');
                url.searchParams.delete('payment_status');
                dirty = true;
            } else if (urlParams.get('payment_status') === 'failed') {
                this.notify('Payment Failed: ' + (urlParams.get('error') || 'Unknown'), 'error');
                url.searchParams.delete('payment_status');
                url.searchParams.delete('error');
                dirty = true;
            }

            if (dirty) {
                window.history.replaceState({}, '', url);
            }
        },
        notify(message, type = 'success') {
            this.flash.message = message;
            this.flash.type = type;
            this.flash.show = true;
            setTimeout(() => {
                this.flash.show = false;
            }, 5000);
        },
        async fetchDashboard() {
            try {
                const res = await axios.get('/wallet/dashboard');
                this.balance = res.data.balance;
                this.currency = res.data.currency;
                this.agreements = res.data.agreements || [];
                
                // Pre-select first agreement if exists
                if (this.agreements.length > 0) {
                    this.selectedAgreementId = this.agreements[0].id; // Most recent due to DB default sort? Or check backend sort.
                } else {
                    this.selectedAgreementId = 'new';
                }
            } catch (error) {
                console.error("Dashboard error", error);
            }
        },
        async linkWallet() {
            try {
                const res = await axios.post('/wallet/link');
                if (res.data.redirect_url) {
                    window.location.href = res.data.redirect_url;
                }
            } catch (error) {
                this.notify('Link Error: ' + (error.response?.data?.error || 'Unknown'), 'error');
            }
        },
        async addMoney() {
            this.errors.amount = '';
            if (!this.amount) {
                this.errors.amount = 'Amount is required';
                return;
            }
            if (this.amount < 1) {
                 this.errors.amount = 'Amount must be greater than 0';
                 return;
            }

            this.loading = true;
            try {
                // Prepare Payload
                const payload = { amount: this.amount };
                
                // If specific agreement selected
                if (this.selectedAgreementId && this.selectedAgreementId !== 'new') {
                    payload.agreement_id = this.selectedAgreementId;
                }
                
                // Note: If 'new' is selected, we send NO agreement_id. 
                // However, backend CURRENTLY defaults to "Latest" if none sent.
                // We need to signal "FORCE NEW". 
                // Let's add 'force_new' param if needed, OR relies on backend update?
                // I'll update backend to respect 'force_new' or similar? 
                // Actually, I can just call /link logic directly if 'new'?
                // But we want the "Add Money" flow (store amount).
                // Let's send `agreement_id: null` and `force_new: true`.
                if (this.selectedAgreementId === 'new') {
                    payload.force_new = true;
                }

                const res = await axios.post('/wallet/add-money', payload);
                
                if (res.data.redirect_url) {
                    window.location.href = res.data.redirect_url;
                } else if (res.data.status === 'success') {
                    this.balance = res.data.balance;
                    this.showAddMoneyModal = false;
                    this.amount = '';
                    this.notify('Payment Successful!', 'success');
                    window.location.reload(); 
                }
            } catch (error) {
                const msg = error.response?.data?.error || 'Payment process failed';
                this.notify(msg, 'error');
                // Backend validation errors logic could go here if using 422
            } finally {
               // keep loading true if redirecting
            }
        },
        toggleLocale() {
            this.locale = this.locale === 'en' ? 'bn' : 'en';
            localStorage.setItem('locale', this.locale);
            window.location.reload();
        },
        logout() {
            localStorage.removeItem('token');
            this.$router.push('/');
        }
    }
}
</script>
