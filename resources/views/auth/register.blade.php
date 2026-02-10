<x-guest-layout>
    <div class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-md rounded-2xl border border-white/15 bg-white/5 p-6 shadow-2xl backdrop-blur">
            <h1 class="mb-6 text-center text-xl font-semibold tracking-wide text-graylight">Create account</h1>

            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf

                <div>
                    <x-input-label for="name" :value="__('Name')" class="text-graylight" />
                    <x-text-input id="name" class="mt-1 block w-full border-white/20 bg-white/5 text-graylight placeholder-gray-400 focus:border-accent focus:ring-accent" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="email" :value="__('Email')" class="text-graylight" />
                    <x-text-input id="email" class="mt-1 block w-full border-white/20 bg-white/5 text-graylight placeholder-gray-400 focus:border-accent focus:ring-accent" type="email" name="email" :value="old('email')" required autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" :value="__('Password')" class="text-graylight" />
                    <x-text-input id="password" class="mt-1 block w-full border-white/20 bg-white/5 text-graylight placeholder-gray-400 focus:border-accent focus:ring-accent"
                        type="password"
                        name="password"
                        required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password_confirmation" :value="__('Confirm Password')" class="text-graylight" />
                    <x-text-input id="password_confirmation" class="mt-1 block w-full border-white/20 bg-white/5 text-graylight placeholder-gray-400 focus:border-accent focus:ring-accent"
                        type="password"
                        name="password_confirmation" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between pt-2">
                    <a class="text-sm underline transition hover:text-accent focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-0" href="{{ route('login') }}">
                        {{ __('Already registered?') }}
                    </a>

                    <x-primary-button class="bg-accent text-dark hover:bg-[#c9beff] focus:bg-[#c9beff] active:bg-[#a99ce9] focus:ring-accent">
                        {{ __('Register') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
