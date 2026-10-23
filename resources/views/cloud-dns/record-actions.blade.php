@if($editable)
    <div class="flex items-center gap-2">
        <x-admin.icon-action label="ویرایش رکورد" icon="edit" tone="primary" @click="openEditor(record)" x-bind:aria-label="'ویرایش ' + record.type + ' ' + record.name" />
        <x-admin.icon-action label="حذف رکورد" icon="delete" tone="danger" @click="deleting = record; $refs.deleter.showModal()" x-bind:aria-label="'حذف ' + record.type + ' ' + record.name" />
    </div>
@else
    <span class="text-xs text-slate-400">فقط مشاهده</span>
@endif
