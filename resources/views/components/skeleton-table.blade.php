@props(['rows' => 5, 'cols' => 5])
<div class="p-3" aria-hidden="true">
    @for ($r = 0; $r < $rows; $r++)
        <div class="d-flex gap-3 mb-3">
            @for ($c = 0; $c < $cols; $c++)
                <span class="skeleton flex-fill" style="opacity: {{ 1 - $r * .12 }}"></span>
            @endfor
        </div>
    @endfor
</div>
