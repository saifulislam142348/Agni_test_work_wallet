<template>
  <div class="min-h-screen bg-gray-100">
    
    <!-- Top Navbar -->
    <header class="bg-white shadow px-6 py-4 flex justify-between items-center">
      <h1 class="text-xl font-bold text-gray-800">Dashboard</h1>
      <button
        @click="logout"
        class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition duration-200"
      >
        Logout
      </button>
    </header>

    <!-- Main Content -->
    <main class="p-6">
      <!-- Welcome Card -->
      <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h2 class="text-2xl font-semibold text-gray-800 mb-2">Welcome Back!</h2>
        <p class="text-gray-600">Here is your dashboard overview.</p>
      </div>

      <!-- Quick Stats Cards -->
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
    </main>
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
