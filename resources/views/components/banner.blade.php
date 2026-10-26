@props(['style' => session('flash.bannerStyle', 'success'), 'message' => session('flash.banner')])

<div x-data="{{ json_encode(['show' => true, 'style' => $style, 'message' => $message]) }}"
     style="display: none;"
     x-show="show && message"
     x-on:banner-message.window="
         style = event.detail.style;
         message = event.detail.message;
         show = true;
     "
     :class="{
         'bg-brand-purple border-brand-purple/60 text-white': style === 'success',
         'bg-red-600 border-red-500 text-white': style === 'danger',
         'bg-brand-yellow border-brand-yellow/60 text-[#111111]': style === 'warning',
         'bg-[#1e1660] border-white/10 text-white': style !== 'success' && style !== 'danger' && style !== 'warning'
     }"
     class="border-b">
    <div class="max-w-6xl mx-auto px-6 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3 min-w-0">
            <svg x-show="style === 'success'" class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <svg x-show="style === 'danger'" class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 5a7 7 0 100 14A7 7 0 0012 5z"/>
            </svg>
            <svg x-show="style === 'warning'" class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <p class="text-sm font-medium truncate" x-text="message"></p>
        </div>
        <button type="button"
                class="flex-shrink-0 ml-4 opacity-70 hover:opacity-100 transition-opacity"
                aria-label="Dismiss"
                x-on:click="show = false">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
            style="display: none;"
            x-show="show && message"
            x-on:banner-message.window="
                style = event.detail.style;
                message = event.detail.message;
                show = true;
            ">
    <div class="max-w-screen-xl mx-auto py-2 px-3 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between flex-wrap">
            <div class="w-0 flex-1 flex items-center min-w-0">
                <span class="flex p-2 rounded-lg" :class="{ 'bg-indigo-600': style == 'success', 'bg-red-600': style == 'danger', 'bg-yellow-600': style == 'warning' }">
                    <svg x-show="style == 'success'" class="size-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg x-show="style == 'danger'" class="size-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <svg x-show="style != 'success' && style != 'danger' && style != 'warning'" class="size-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    <svg x-show="style == 'warning'" class="size-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" fill="none" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4v.01 0 0 " />
                    </svg>
                </span>

                <p class="ms-3 font-medium text-sm text-white truncate" x-text="message"></p>
            </div>

            <div class="shrink-0 sm:ms-3">
                <button
                    type="button"
                    class="-me-1 flex p-2 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:-me-2 transition"
                    :class="{ 'hover:bg-indigo-600 focus:bg-indigo-600': style == 'success', 'hover:bg-red-600 focus:bg-red-600': style == 'danger', 'hover:bg-yellow-600 focus:bg-yellow-600': style == 'warning'}"
                    aria-label="Dismiss"
                    x-on:click="show = false">
                    <svg class="size-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>
