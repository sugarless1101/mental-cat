<x-guest-layout>
    <div class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-md rounded-2xl border border-white/15 bg-white/5 p-6 shadow-2xl backdrop-blur">
            <h1 class="mb-6 text-center text-xl font-semibold tracking-wide text-graylight">Log in</h1>

            <x-auth-session-status class="mb-4 text-graylight" :status="session('status')" />

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <x-input-label for="email" :value="__('Email')" class="text-graylight" />
                    <x-text-input id="email" class="mt-1 block w-full border-white/20 bg-white/5 text-graylight placeholder-gray-400 focus:border-accent focus:ring-accent" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" :value="__('Password')" class="text-graylight" />
                    <x-text-input id="password" class="mt-1 block w-full border-white/20 bg-white/5 text-graylight placeholder-gray-400 focus:border-accent focus:ring-accent"
                        type="password"
                        name="password"
                        required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between">
                    <label for="remember_me" class="inline-flex items-center">
                        <input id="remember_me" type="checkbox" class="rounded border-white/30 bg-white/5 text-accent shadow-sm focus:ring-accent" name="remember">
                        <span class="ms-2 text-sm text-graylight">{{ __('Remember me') }}</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a class="text-sm underline transition hover:text-accent focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-0" href="{{ route('password.request') }}">
                            {{ __('Forgot your password?') }}
                        </a>
                    @endif
                </div>

                <div class="flex items-center justify-between pt-2">
                    <a class="text-sm underline transition hover:text-accent focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-0" href="{{ route('register') }}">
                        {{ __('Create account') }}
                    </a>

                    <x-primary-button class="bg-accent text-dark hover:bg-[#c9beff] focus:bg-[#c9beff] active:bg-[#a99ce9] focus:ring-accent">
                        {{ __('Log in') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
