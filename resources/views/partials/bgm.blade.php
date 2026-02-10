<div class="bg-white rounded-lg shadow p-6">
    <h2 class="text-xl font-semibold mb-4">BGM</h2>
    @php
        $map = config('bgm');
        $id = $map[$bgmKey] ?? ($map['calm'] ?? null);
    @endphp
    <div class="aspect-w-16 aspect-h-9">
        <iframe id="bgm-iframe" width="100%" height="200" src="https://www.youtube.com/embed/{{ $id }}?mute=1" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
    </div>
</div>
