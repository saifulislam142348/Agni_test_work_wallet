<template>
  <div class="min-h-screen bg-gray-100 p-6">
    <header class="flex justify-between items-center bg-white shadow px-6 py-4 mb-6 rounded-xl">
      <h1 class="text-xl font-bold">Dashboard</h1>
      <button @click="logout" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg">Logout</button>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <div class="bg-white shadow rounded-xl p-6">
        <h3 class="text-gray-500 text-sm mb-2">Users</h3>
        <p class="text-2xl font-bold text-gray-800">1,234</p>
      </div>
      <div class="bg-white shadow rounded-xl p-6">
        <h3 class="text-gray-500 text-sm mb-2">Orders</h3>
        <p class="text-2xl font-bold text-gray-800">567</p>
      </div>
      <div class="bg-white shadow rounded-xl p-6">
        <h3 class="text-gray-500 text-sm mb-2">Revenue</h3>
        <p class="text-2xl font-bold text-gray-800">$12,345</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
const router = useRouter()

const logout = () => {
  localStorage.removeItem('token')
  router.push('/')
}
</script>
