<x-layouts.app :title="'Login | Qwerty Ticket System'">
    <section class="auth-shell min-h-screen p-4 lg:p-8">
        <div
            class="auth-card panel mx-auto grid w-full max-w-6xl overflow-hidden rounded-2xl border lg:grid-cols-[1.05fr_0.95fr]">
            <aside class="relative overflow-hidden border-b border-slate-200/80 p-6 md:p-10 lg:border-b-0 lg:border-r">
                <div class="absolute -left-16 top-8 h-36 w-36 rounded-full bg-cyan-300/45 blur-3xl"></div>
                <div class="absolute -right-10 bottom-2 h-40 w-40 rounded-full bg-sky-300/40 blur-3xl"></div>

                <p class="relative text-xs font-extrabold uppercase tracking-[0.08em] text-cyan-700">
                    Qwerty Support Platform
                </p>
                <h1 class="relative mt-3 text-3xl font-extrabold leading-tight text-slate-900 md:text-5xl">
                    Welcome back to your service desk
                </h1>
                <p class="relative mt-5 max-w-xl text-sm leading-6 text-slate-600 md:text-base">
                    Sign in to handle incidents, route requests, and monitor SLA response times from one workspace.
                </p>
            </aside>

            <div class="bg-white/80 p-6 md:p-10">
                <div class="mx-auto w-full max-w-md">
                    <h2 class="text-2xl font-bold text-slate-900">Sign in</h2>
                    <p class="mt-2 text-sm text-slate-600">Use your workspace credentials to continue.</p>

                    <form method="POST" action="{{ route('login.store') }}" class="mt-6 grid gap-4">
                        @csrf
                        @include('partials.alerts')

                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                            Work email
                            <input name="email" type="email" placeholder="name@company.com" value="{{ old('email') }}"
                                required class="rounded-lg px-3 py-2.5 text-sm" />
                            @error('email')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="grid gap-1.5 text-sm font-semibold text-slate-700">
                            Password
                            <input name="password" type="password" placeholder="Enter your password" required
                                class="rounded-lg px-3 py-2.5 text-sm" />
                            @error('password')
                                <span class="text-xs font-medium text-red-600">{{ $message }}</span>
                            @enderror
                        </label>

                        <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" name="remember" value="1" class="rounded" />
                                Keep me signed in
                            </label>
                            <span class="text-xs font-medium uppercase tracking-[0.08em] text-slate-500">Secure
                                login</span>
                        </div>

                        <button type="submit"
                            class="btn btn-primary rounded-lg px-4 py-2.5 text-sm font-bold text-white">
                            Sign in to dashboard
                        </button>
                    </form>

                    <p class="mt-5 text-xs text-slate-500">
                        Need access? Contact your administrator to request an account.
                    </p>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
