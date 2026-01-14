<template>
    <div class="bg-white shadow rounded-lg p-6 mt-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">{{ t.history_title }}</h3>
            <button @click="downloadStatement" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-700 transition flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                {{ t.download_stmt }}
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full leading-normal">
                <thead>
                    <tr>
                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            {{ t.date }}
                        </th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            {{ t.trx_id }}
                        </th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            {{ t.type }}
                        </th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            {{ t.amount }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="trx in transactions" :key="trx.id">
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                            {{ formatDate(trx.created_at) }}
                        </td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                            {{ trx.trx_id }}
                        </td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                            <span :class="{'text-green-600': trx.type === 'credit', 'text-red-600': trx.type === 'debit'}" class="capitalize font-semibold">
                                {{ trx.type }}
                            </span>
                        </td>
                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm font-bold">
                            {{ trx.amount }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Simple Pagination if needed -->
    </div>
</template>

<script>
import { translations } from '../lang';

export default {
    props: ['locale'],
    data() {
        return {
            transactions: []
        }
    },
    computed: {
        t() {
            return translations[this.locale] || translations.en;
        }
    },
    mounted() {
        this.fetchHistory();
    },
    methods: {
        async fetchHistory() {
            try {
                const res = await axios.get('/wallet/history');
                this.transactions = res.data.data;
            } catch (error) {
                console.error(error);
            }
        },
        async downloadStatement() {
            try {
                const res = await axios.get('/wallet/statement/download', {
                    responseType: 'blob' // Important: Axios treats JSON error as blob too if 503
                });
                
                // If it's a JSON error blob (service unavailable)
                if (res.data.type === 'application/json') {
                    const error = JSON.parse(await res.data.text());
                    alert('⚠️ ' + (error.message || error.error));
                    return;
                }

                const url = window.URL.createObjectURL(new Blob([res.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', 'statement.pdf');
                document.body.appendChild(link);
                link.click();
            } catch (error) {
                // If response is a blob (from 503), read it
                if (error.response && error.response.data instanceof Blob) {
                     const errorText = await error.response.data.text();
                     try {
                        const errorJson = JSON.parse(errorText);
                        alert('⚠️ Service Error: ' + errorJson.message);
                     } catch(e) {
                        alert('Failed to download statement: Service Unavailable');
                     }
                } else {
                    alert('Failed to download statement.');
                }
            }
        },
        formatDate(date) {
            return new Date(date).toLocaleDateString() + ' ' + new Date(date).toLocaleTimeString();
        }
    },
    watch: {
        locale() {
            // refresh if needed or just reactive
        }
    }
}
</script>
