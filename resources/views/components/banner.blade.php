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
         'clear': style === 'success',
         'stop': style === 'danger',
         'hold': style === 'warning'
     }"
     class="notice acct-banner"
     role="status">
    <p class="acct-banner-text" x-text="message"></p>
    <button type="button"
            class="icon-btn"
            aria-label="Dismiss"
            x-on:click="show = false">
        <x-sig.icon name="close" />
    </button>
</div>
