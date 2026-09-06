<script setup lang="ts">
const props = defineProps<{
    groups: App.Data.PermissionGroupData[];
    modelValue: string[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

function names(group: App.Data.PermissionGroupData): string[] {
    return group.permissions.map((p) => p.name);
}

function isChecked(name: string): boolean {
    return props.modelValue.includes(name);
}

function toggle(name: string) {
    const current = [...props.modelValue];
    const index = current.indexOf(name);

    if (index === -1) {
        current.push(name);
    } else {
        current.splice(index, 1);
    }

    emit('update:modelValue', current);
}

function isGroupAllChecked(group: App.Data.PermissionGroupData): boolean {
    return names(group).every((name) => props.modelValue.includes(name));
}

function isGroupPartial(group: App.Data.PermissionGroupData): boolean {
    const groupNames = names(group);
    const checkedCount = groupNames.filter((name) => props.modelValue.includes(name)).length;
    return checkedCount > 0 && checkedCount < groupNames.length;
}

function toggleGroup(group: App.Data.PermissionGroupData) {
    const allChecked = isGroupAllChecked(group);
    const current = [...props.modelValue];

    for (const name of names(group)) {
        const index = current.indexOf(name);
        if (allChecked && index !== -1) {
            current.splice(index, 1);
        } else if (!allChecked && index === -1) {
            current.push(name);
        }
    }

    emit('update:modelValue', current);
}
</script>

<template>
    <div class="space-y-4">
        <div v-for="group in groups" :key="group.group" class="card bg-base-200">
            <div class="card-body p-4">
                <div class="flex items-center gap-3 mb-2">
                    <input
                        type="checkbox"
                        class="checkbox checkbox-sm"
                        :checked="isGroupAllChecked(group)"
                        :indeterminate="isGroupPartial(group)"
                        :aria-label="group.group_label"
                        @change="toggleGroup(group)"
                    />
                    <h4 class="font-semibold">{{ group.group_label }}</h4>
                </div>
                <div class="flex flex-wrap gap-4 pl-7">
                    <label v-for="p in group.permissions" :key="p.id" class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            class="checkbox checkbox-sm checkbox-primary"
                            :checked="isChecked(p.name)"
                            @change="toggle(p.name)"
                        />
                        <span class="text-sm">{{ p.label }}</span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>
