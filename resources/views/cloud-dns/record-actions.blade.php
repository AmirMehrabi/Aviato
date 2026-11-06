@if($editable)
    <button type="button" @click="openEditor(record)" :aria-label="'ویرایش ' + record.type + ' ' + record.name" :aria-expanded="editorOpen && editingRecord?.id === record.id" class="rounded-lg px-3 py-2 text-sm font-medium text-[#0069FF] hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-blue-600">ویرایش</button>
@else
    <span class="text-xs text-slate-400">فقط مشاهده</span>
@endif
