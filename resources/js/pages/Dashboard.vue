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
                    <button @click="showAddMoneyModal = true" :disabled="!hasAgreement" 
                        class="bg-white text-pink-600 px-6 py-2 rounded font-bold shadow hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed transition">
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
        <div v-if="showAddMoneyModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
            <div class="bg-white rounded-lg p-8 w-96 shadow-xl">
                <h3 class="text-lg font-bold mb-4">{{ t.add_money }}</h3>
                <input v-model="amount" type="number" placeholder="Amount (BDT)" class="w-full border p-2 rounded mb-4" />
                <div class="flex justify-end space-x-2">
                    <button @click="showAddMoneyModal = false" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">Cancel</button>
                    <button @click="addMoney" :disabled="loading" class="px-4 py-2 bg-pink-600 text-white rounded hover:bg-pink-700">
                        {{ loading ? t.processing : 'Confirm' }}
                    </button>
                </div>
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
            hasAgreement: false,
            agreementPhone: null,
            showAddMoneyModal: false,
            amount: '',
            loading: false
        }
    },
    computed: {
        t() {
            return translations[this.locale] || translations.en;
        }
    },
    mounted() {
        this.fetchDashboard();
    },
    methods: {
        async fetchDashboard() {
            try {
                const res = await axios.get('/wallet/dashboard');
                this.balance = res.data.balance;
                this.currency = res.data.currency;
                this.hasAgreement = res.data.has_agreement;
                this.agreementPhone = res.data.agreement_phone;
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
                alert('Link Error: ' + error.response?.data?.error || 'Unknown');
            }
        },
        async addMoney() {
            if (!this.amount) return;
            this.loading = true;
            try {
                const res = await axios.post('/wallet/add-money', { amount: this.amount });
                if (res.data.status === 'success') {
                    this.balance = res.data.balance;
                    this.showAddMoneyModal = false;
                    this.amount = '';
                    alert(this.t.success);
                    // Reload history implies refreshing component, simplified here by page reload or event bus
                    window.location.reload(); 
                }
            } catch (error) {
                alert('Payment Error: ' + (error.response?.data?.error || 'Error'));
            } finally {
                this.loading = false;
            }
        },
        toggleLocale() {
            this.locale = this.locale === 'en' ? 'bn' : 'en';
            localStorage.setItem('locale', this.locale);
            window.location.reload(); // Simple reload to refresh all components/axios headers
        },
        logout() {
            localStorage.removeItem('token');
            this.$router.push('/');
        }
    }
}
</script>
