<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Welcome" />

    <main class="flex min-h-screen items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
        <div class="w-full max-w-2xl rounded-2xl border border-[#e3e3e0] bg-white p-10 text-center dark:border-[#3E3E3A] dark:bg-[#161615]">
            <h1 class="text-3xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">
                Starter Kit Vue (No Auth)
            </h1>
            <p class="mt-4 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                Base pronta para começar, sem scaffold de autenticação.
            </p>
        </div>
    </main>
</template>
