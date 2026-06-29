<?php
/**
 * Editor de texto visual (WYSIWYG) básico, nativo (contenteditable + execCommand).
 * Sin dependencias, compatible con todos los navegadores.
 *
 * Uso:  <?= View::partial('partials/wysiwyg', ['name' => 'cuerpo', 'value' => $html]) ?>
 *
 * @var string $name   nombre del campo (se envía como HTML en un <textarea> oculto)
 * @var string $value  HTML inicial
 * @var string $height alto mínimo del área (ej. '12rem')
 */
use Core\View;

$name = $name ?? 'content';
$value = $value ?? '';
$height = $height ?? '12rem';
$placeholder = $placeholder ?? 'Escribí acá…';
?>
<div x-data="wysiwyg()" class="overflow-hidden rounded-lg border border-slate-300 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
    <div class="flex flex-wrap items-center gap-0.5 border-b border-slate-200 bg-slate-50 px-1.5 py-1">
        <button type="button" @click="cmd('bold')" class="wy-btn font-bold" title="Negrita">B</button>
        <button type="button" @click="cmd('italic')" class="wy-btn italic" title="Cursiva">I</button>
        <button type="button" @click="cmd('underline')" class="wy-btn underline" title="Subrayado">U</button>
        <span class="mx-1 h-4 w-px bg-slate-300"></span>
        <button type="button" @click="block('h2')" class="wy-btn" title="Título">H2</button>
        <button type="button" @click="block('p')" class="wy-btn" title="Párrafo">¶</button>
        <button type="button" @click="cmd('insertUnorderedList')" class="wy-btn" title="Viñetas">•≡</button>
        <button type="button" @click="cmd('insertOrderedList')" class="wy-btn" title="Numerada">1.</button>
        <span class="mx-1 h-4 w-px bg-slate-300"></span>
        <button type="button" @click="link()" class="wy-btn" title="Enlace">🔗</button>
        <button type="button" @click="cmd('removeFormat')" class="wy-btn" title="Limpiar formato">⌫</button>
    </div>
    <div x-ref="ed" contenteditable="true" @input="sync()" @blur="sync()"
         data-placeholder="<?= View::e($placeholder) ?>"
         class="wy-editor max-w-none overflow-y-auto px-3 py-2 text-sm text-slate-700 focus:outline-none"
         style="min-height: <?= View::e($height) ?>; max-height: 28rem"><?= $value ?></div>
    <textarea x-ref="input" name="<?= View::e($name) ?>" class="hidden"><?= View::e($value) ?></textarea>
</div>
