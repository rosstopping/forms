@props(['id', 'name', 'value' => null, 'placeholder' => null])

<input id="{{ $id }}-input" type="hidden" name="{{ $name }}" value="{{ $value }}">
<trix-toolbar id="{{ $id }}-toolbar" class="trix-toolbar">
    <div class="trix-button-row">
        <span class="trix-button-group trix-button-group--text-tools" data-trix-button-group="text-tools">
            <button type="button" class="trix-button trix-button--icon trix-button--icon-bold" data-trix-attribute="bold" data-trix-key="b" title="Bold" tabindex="-1">Bold</button>
            <button type="button" class="trix-button trix-button--icon trix-button--icon-italic" data-trix-attribute="italic" data-trix-key="i" title="Italic" tabindex="-1">Italic</button>
        </span>
        <span class="trix-button-group trix-button-group--block-tools" data-trix-button-group="block-tools">
            <button type="button" class="trix-button trix-button--icon trix-button--icon-bullet-list" data-trix-attribute="bullet" title="Bullets" tabindex="-1">Bullets</button>
            <button type="button" class="trix-button trix-button--icon trix-button--icon-number-list" data-trix-attribute="number" title="Numbers" tabindex="-1">Numbers</button>
        </span>
        <span class="trix-button-group trix-button-group--history-tools" data-trix-button-group="history-tools">
            <button type="button" class="trix-button trix-button--icon trix-button--icon-undo" data-trix-action="undo" data-trix-key="z" title="Undo" tabindex="-1">Undo</button>
            <button type="button" class="trix-button trix-button--icon trix-button--icon-redo" data-trix-action="redo" data-trix-key="shift+z" title="Redo" tabindex="-1">Redo</button>
        </span>
    </div>
</trix-toolbar>
<trix-editor
    id="{{ $id }}"
    input="{{ $id }}-input"
    toolbar="{{ $id }}-toolbar"
    placeholder="{{ $placeholder }}"
    {{ $attributes->class(['trix-content autoresponder-editor']) }}
></trix-editor>
