{{-- Botovis Chat Widget --}}
{{-- Usage: @botovisWidget or @botovisWidget(['lang' => 'en', 'theme' => 'dark']) --}}

<botovis-chat
    endpoint="{{ $endpoint ?? '/' . config('botovis.route.prefix', 'botovis') }}"
    lang="{{ $lang ?? 'tr' }}"
    theme="{{ $theme ?? 'auto' }}"
    position="{{ $position ?? 'bottom-right' }}"
    @if(!empty($title)) title="{{ $title }}" @endif
    @if(!empty($placeholder)) placeholder="{{ $placeholder }}" @endif
    csrf-token="{{ csrf_token() }}"
></botovis-chat>

<script src="{{ asset('vendor/botovis/botovis-widget.iife.js') }}"></script>
