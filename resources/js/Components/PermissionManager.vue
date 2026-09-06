<script setup lang="ts">
import { computed } from 'vue';

interface Permission {
    id: string | number;
    name: string;
}

const props = defineProps<{
    permissions: Permission[];
    modelValue: string[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

// Group permissions by resource (last word after the action verb)
const grouped = computed(() => {
    const groups: Record<string, Permission[]> = {};

    for (const permission of props.permissions) {
        const parts = permission.name.split(' ');
        // e.g. "view users" -> resource = "users"
        const resource = parts.slice(1).join(' ') || parts[0];

        if (!groups[resource]) {
            groups[resource] = [];
        }
        groups[resource].push(permission);
    }

    return groups;
});

function isChecked(permissionName: string): boolean {
    return props.modelValue.includes(permissionName);
}

function toggle(permissionName: string) {
    const current = [...props.modelValue];
    const index = current.indexOf(permissionName);

    if (index === -1) {
        current.push(permissionName);
    } else {
        current.splice(index, 1);
    }

    emit('update:modelValue', current);
}

function isGroupAllChecked(resource: string): boolean {
    return (grouped.value[resource] ?? []).every((p) => props.modelValue.includes(p.name));
}

function toggleGroup(resource: string) {
    const group = grouped.value[resource] ?? [];
    const allChecked = isGroupAllChecked(resource);
    const current = [...props.modelValue];

    for (const permission of group) {
        const index = current.indexOf(permission.name);
        if (allChecked && index !== -1) {
            current.splice(index, 1);
        } else if (!allChecked && index === -1) {
            current.push(permission.name);
        }
    }

    emit('update:modelValue', current);
}
</script>

<template>
    <div class="space-y-4">
        <div v-for="(group, resource) in grouped" :key="resource" class="card bg-base-200">
            <div class="card-body p-4">
                <div class="flex items-center gap-3 mb-2">
                    <input
                        type="checkbox"
                        class="checkbox checkbox-sm"
                        :checked="isGroupAllChecked(String(resource))"
                        @change="toggleGroup(String(resource))"
                    />
                    <h4 class="font-semibold capitalize">{{ resource }}</h4>
                </div>
                <div class="flex flex-wrap gap-4 pl-7">
                    <label
                        v-for="permission in group"
                        :key="permission.id"
                        class="flex items-center gap-2 cursor-pointer"
                    >
                        <input
                            type="checkbox"
                            class="checkbox checkbox-sm checkbox-primary"
                            :checked="isChecked(permission.name)"
                            @change="toggle(permission.name)"
                        />
                        <span class="text-sm capitalize">
                            {{ permission.name.split(' ')[0] }}
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>
