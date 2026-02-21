<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

type ScheduleEvent = {
    unit: string;
    optionCode: string;
    classType: string;
    day: string;
    dayIndex: number;
    start: string;
    end: string;
    mandatory: boolean;
    startMinutes: number;
    endMinutes: number;
};

type ScheduleMetrics = {
    daysCount: number;
    gaps: number;
    earliest: string | null;
    latest: string | null;
    totalMinutes: number;
    balance: number;
    score: number;
};

type Schedule = {
    key: string;
    events: ScheduleEvent[];
    metrics: ScheduleMetrics;
};

type UnitOption = {
    code: string;
    events: ScheduleEvent[];
};

type UnitCatalog = {
    name: string;
    mandatoryEvents: ScheduleEvent[];
    options: UnitOption[];
};

type UnitChoiceGroup = {
    classType: string;
    options: UnitOption[];
};

type UnitWithGroups = {
    name: string;
    mandatoryEvents: ScheduleEvent[];
    choiceGroups: UnitChoiceGroup[];
};

const props = defineProps<{
    meta: {
        filePath: string;
        uploadedFileName: string | null;
        totalSchedules: number;
        totalUnits: number;
    };
    schedules: Schedule[];
    units: UnitCatalog[];
}>();

const filters = reactive({
    search: '',
    maxDays: 6,
    maxGaps: 999,
    sortBy: 'score' as 'score' | 'days' | 'gaps' | 'earliest',
});

const selectedOptionsByGroup = reactive<Record<string, string[]>>({});

const dayNames: Record<number, string> = {
    1: 'Seg',
    2: 'Ter',
    3: 'Qua',
    4: 'Qui',
    5: 'Sex',
    6: 'Sab',
};

const classTypeOrder = ['T', 'TP', 'PL'];

const colorPalette = [
    'from-sky-100 to-sky-50 border-sky-300 text-sky-900',
    'from-violet-100 to-violet-50 border-violet-300 text-violet-900',
    'from-emerald-100 to-emerald-50 border-emerald-300 text-emerald-900',
    'from-amber-100 to-amber-50 border-amber-300 text-amber-900',
    'from-pink-100 to-pink-50 border-pink-300 text-pink-900',
    'from-blue-100 to-blue-50 border-blue-300 text-blue-900',
];

function hashUnit(unit: string): number {
    let hash = 0;

    for (let index = 0; index < unit.length; index++) {
        hash = (hash << 5) - hash + unit.charCodeAt(index);
        hash |= 0;
    }

    return Math.abs(hash);
}

function colorClassForUnit(unit: string): string {
    return colorPalette[hashUnit(unit) % colorPalette.length];
}

function normalizeClassType(value: string): string {
    const normalized = value.trim().toUpperCase().replace(/\s+/g, '');

    if (normalized === '' || normalized === 'OBRIGATORIO') {
        return 'OUTRO';
    }

    if (normalized.startsWith('TP')) {
        return 'TP';
    }

    if (normalized.startsWith('PL')) {
        return 'PL';
    }

    if (normalized === 'T' || normalized.startsWith('TEO')) {
        return 'T';
    }

    return normalized;
}

function optionClassType(option: UnitOption): string {
    const types = Array.from(new Set(option.events.map((event) => normalizeClassType(event.classType)).filter((value) => value !== 'OUTRO')));

    if (types.length === 0) {
        return 'OUTRO';
    }

    const sorted = [...types].sort((left, right) => {
        const leftIndex = classTypeOrder.indexOf(left);
        const rightIndex = classTypeOrder.indexOf(right);

        if (leftIndex === -1 && rightIndex === -1) {
            return left.localeCompare(right);
        }

        if (leftIndex === -1) {
            return 1;
        }

        if (rightIndex === -1) {
            return -1;
        }

        return leftIndex - rightIndex;
    });

    return sorted[0];
}

function groupKey(unitName: string, classType: string): string {
    return `${unitName}::${classType}`;
}

const unitChoices = computed<UnitWithGroups[]>(() => {
    return props.units.map((unit) => {
        const groupedOptions: Record<string, UnitOption[]> = {};

        for (const option of unit.options) {
            const classType = optionClassType(option);

            groupedOptions[classType] ??= [];
            groupedOptions[classType].push(option);
        }

        const choiceGroups = Object.entries(groupedOptions)
            .map(([classType, options]) => ({
                classType,
                options: [...options].sort((left, right) => left.code.localeCompare(right.code)),
            }))
            .sort((left, right) => {
                const leftIndex = classTypeOrder.indexOf(left.classType);
                const rightIndex = classTypeOrder.indexOf(right.classType);

                if (leftIndex === -1 && rightIndex === -1) {
                    return left.classType.localeCompare(right.classType);
                }

                if (leftIndex === -1) {
                    return 1;
                }

                if (rightIndex === -1) {
                    return -1;
                }

                return leftIndex - rightIndex;
            });

        return {
            name: unit.name,
            mandatoryEvents: unit.mandatoryEvents,
            choiceGroups,
        };
    });
});

function overlaps(a: ScheduleEvent, b: ScheduleEvent): boolean {
    return a.dayIndex === b.dayIndex && a.startMinutes < b.endMinutes && b.startMinutes < a.endMinutes;
}

const filteredSchedules = computed(() => {
    const search = filters.search.toLowerCase().trim();

    const entries = props.schedules.filter((schedule) => {
        if (search !== '' && !schedule.key.toLowerCase().includes(search)) {
            return false;
        }

        if (schedule.metrics.daysCount > filters.maxDays || schedule.metrics.gaps > filters.maxGaps) {
            return false;
        }

        return true;
    });

    return [...entries].sort((left, right) => {
        if (filters.sortBy === 'days') {
            return left.metrics.daysCount - right.metrics.daysCount;
        }

        if (filters.sortBy === 'gaps') {
            return left.metrics.gaps - right.metrics.gaps;
        }

        if (filters.sortBy === 'earliest') {
            return (left.metrics.earliest ?? '00:00').localeCompare(right.metrics.earliest ?? '00:00');
        }

        return left.metrics.score - right.metrics.score;
    });
});

const selectedTags = computed(() => {
    const tags: Array<{ unit: string; code: string; classType: string; mandatory: boolean }> = [];

    for (const unit of unitChoices.value) {
        if (unit.mandatoryEvents.length > 0) {
            tags.push({ unit: unit.name, code: 'OBR', classType: 'FIXO', mandatory: true });
        }

        for (const group of unit.choiceGroups) {
            const selectedCodes = selectedOptionsByGroup[groupKey(unit.name, group.classType)] ?? [];

            for (const selectedCode of selectedCodes) {
                tags.push({ unit: unit.name, code: selectedCode, classType: group.classType, mandatory: false });
            }
        }
    }

    return tags;
});

const builderEvents = computed(() => {
    const events: ScheduleEvent[] = [];

    for (const unit of unitChoices.value) {
        events.push(...unit.mandatoryEvents);

        for (const group of unit.choiceGroups) {
            const selectedCodes = selectedOptionsByGroup[groupKey(unit.name, group.classType)] ?? [];

            if (selectedCodes.length === 0) {
                continue;
            }

            const selectedOptions = group.options.filter((option) => selectedCodes.includes(option.code));

            for (const selected of selectedOptions) {
                events.push(...selected.events);
            }
        }
    }

    return [...events].sort((left, right) => {
        if (left.dayIndex === right.dayIndex) {
            return left.startMinutes - right.startMinutes;
        }

        return left.dayIndex - right.dayIndex;
    });
});

const collisionSignatures = computed(() => {
    const collisions = new Set<string>();

    for (let index = 0; index < builderEvents.value.length; index++) {
        for (let next = index + 1; next < builderEvents.value.length; next++) {
            const first = builderEvents.value[index];
            const second = builderEvents.value[next];

            if (overlaps(first, second)) {
                collisions.add(eventKey(first));
                collisions.add(eventKey(second));
            }
        }
    }

    return collisions;
});

const daysToShow = computed(() => {
    const base = [1, 2, 3, 4, 5];

    if (builderEvents.value.some((event) => event.dayIndex === 6)) {
        base.push(6);
    }

    return base;
});

const timelineStart = computed(() => {
    if (builderEvents.value.length === 0) {
        return 8 * 60;
    }

    const earliest = Math.min(...builderEvents.value.map((event) => event.startMinutes));

    return Math.floor(earliest / 60) * 60;
});

const timelineEnd = computed(() => {
    if (builderEvents.value.length === 0) {
        return 20 * 60;
    }

    const latest = Math.max(...builderEvents.value.map((event) => event.endMinutes));

    return Math.ceil(latest / 60) * 60;
});

const timelineHours = computed(() => {
    const labels: number[] = [];

    for (let minute = timelineStart.value; minute <= timelineEnd.value; minute += 60) {
        labels.push(minute);
    }

    return labels;
});

const eventsByDay = computed(() => {
    const map: Record<number, ScheduleEvent[]> = { 1: [], 2: [], 3: [], 4: [], 5: [], 6: [] };

    for (const event of builderEvents.value) {
        map[event.dayIndex].push(event);
    }

    return map;
});

function eventKey(event: ScheduleEvent): string {
    return `${event.unit}|${event.optionCode}|${event.dayIndex}|${event.start}|${event.end}`;
}

function eventTop(event: ScheduleEvent): string {
    const total = timelineEnd.value - timelineStart.value;

    if (total <= 0) {
        return '0%';
    }

    return `${((event.startMinutes - timelineStart.value) / total) * 100}%`;
}

function eventHeight(event: ScheduleEvent): string {
    const total = timelineEnd.value - timelineStart.value;

    if (total <= 0) {
        return '8%';
    }

    return `${Math.max(4, ((event.endMinutes - event.startMinutes) / total) * 100)}%`;
}

function hourLabel(minutes: number): string {
    const hour = Math.floor(minutes / 60);

    return `${String(hour).padStart(2, '0')}:00`;
}

function toggleOption(unit: string, classType: string, code: string): void {
    const key = groupKey(unit, classType);
    const selectedCodes = selectedOptionsByGroup[key] ?? [];

    if (selectedCodes.includes(code)) {
        const remaining = selectedCodes.filter((value) => value !== code);

        if (remaining.length === 0) {
            delete selectedOptionsByGroup[key];

            return;
        }

        selectedOptionsByGroup[key] = remaining;

        return;
    }

    selectedOptionsByGroup[key] = [...selectedCodes, code];
}

function isOptionSelected(unit: string, classType: string, code: string): boolean {
    return (selectedOptionsByGroup[groupKey(unit, classType)] ?? []).includes(code);
}

function clearAllSelections(): void {
    for (const key of Object.keys(selectedOptionsByGroup)) {
        delete selectedOptionsByGroup[key];
    }
}

function applyScheduleToBuilder(schedule: Schedule): void {
    clearAllSelections();

    for (const event of schedule.events) {
        if (event.mandatory) {
            continue;
        }

        const unit = event.unit;
        const classType = normalizeClassType(event.classType);
        const key = groupKey(unit, classType);

        if (key !== '') {
            const selectedCodes = selectedOptionsByGroup[key] ?? [];

            if (! selectedCodes.includes(event.optionCode)) {
                selectedOptionsByGroup[key] = [...selectedCodes, event.optionCode];
            }
        }
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<template>
    <Head title="Schedule Builder" />

    <main class="min-h-screen bg-slate-50 px-4 py-8 text-slate-900 sm:px-6 lg:px-8">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-semibold">Escolhe turnos e monta o teu horário</h1>
                        <p class="mt-1 text-sm text-slate-600">Seleciona T/TP/PL por UC, vê conflitos no calendário e exporta resultados.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="/turns/export" class="rounded-lg border border-blue-300 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-800 hover:bg-blue-100">
                            Exportar por texto
                        </a>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            @click="clearAllSelections"
                        >
                            Limpar escolhas
                        </button>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-for="tag in selectedTags"
                        :key="`${tag.unit}-${tag.classType}-${tag.code}`"
                        class="rounded-full border px-3 py-1 text-xs"
                        :class="tag.mandatory ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-sky-300 bg-sky-50 text-sky-800'"
                    >
                        {{ tag.unit }} {{ tag.classType }} {{ tag.code }}
                    </span>
                    <span v-if="selectedTags.length === 0" class="text-xs text-slate-500">Ainda sem escolhas. Usa os botões por tipo para começar.</span>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h1 class="text-xl font-semibold">Escolher horários por tipo (PL, TP, T)</h1>
                <p class="mt-1 text-sm text-slate-600">Cada grupo de tipo pode ter uma ou mais escolhas. Eventos obrigatórios entram automaticamente.</p>
                <p v-if="meta.uploadedFileName" class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                    Horário carregado a partir do texto: {{ meta.uploadedFileName }}
                </p>

                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <article v-for="unit in unitChoices" :key="unit.name" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-semibold">{{ unit.name }}</h2>
                            <span v-if="unit.mandatoryEvents.length > 0" class="rounded-full border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-[11px] text-emerald-700">OBR</span>
                        </div>

                        <div class="mt-3 flex flex-col gap-3">
                            <div v-for="group in unit.choiceGroups" :key="`${unit.name}-${group.classType}`" class="rounded-lg border border-slate-200 bg-white p-2">
                                <div class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ group.classType }}</div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="option in group.options"
                                        :key="`${unit.name}-${group.classType}-${option.code}`"
                                        type="button"
                                        @click="toggleOption(unit.name, group.classType, option.code)"
                                        class="rounded-full border px-3 py-1 text-xs transition"
                                        :class="isOptionSelected(unit.name, group.classType, option.code) ? 'border-sky-400 bg-sky-100 text-sky-900' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400'"
                                    >
                                        {{ option.code }}
                                    </button>
                                    <span v-if="group.options.length === 0" class="text-xs text-slate-500">Sem opcoes.</span>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-xl font-semibold">Calendario semanal</h2>

                <p v-if="collisionSignatures.size > 0" class="mt-3 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                    Existem sobreposicoes entre turnos selecionados.
                </p>

                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <div class="min-w-[820px]">
                        <div class="grid border-b border-slate-200" :style="`grid-template-columns: 88px repeat(${daysToShow.length}, minmax(0, 1fr));`">
                            <div class="border-r border-slate-200 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Hora</div>
                            <div v-for="day in daysToShow" :key="`head-${day}`" class="border-r border-slate-200 px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-slate-700 last:border-r-0">
                                {{ dayNames[day] }}
                            </div>
                        </div>

                        <div class="grid" :style="`grid-template-columns: 88px repeat(${daysToShow.length}, minmax(0, 1fr));`">
                            <div class="border-r border-slate-200">
                                <div v-for="hour in timelineHours" :key="`hour-${hour}`" class="h-16 border-b border-slate-200 px-3 py-2 text-xs text-slate-500">
                                    {{ hourLabel(hour) }}
                                </div>
                            </div>

                            <div
                                v-for="day in daysToShow"
                                :key="`col-${day}`"
                                class="relative border-r border-slate-200 last:border-r-0"
                                :style="`height: ${Math.max(1, timelineHours.length - 1) * 64}px;`"
                            >
                                <div
                                    v-for="hour in timelineHours.slice(0, -1)"
                                    :key="`line-${day}-${hour}`"
                                    class="h-16 border-b border-slate-200"
                                />

                                <div
                                    v-for="event in eventsByDay[day]"
                                    :key="eventKey(event)"
                                    class="absolute left-1 right-1 rounded-xl border bg-gradient-to-b p-2 text-[11px] shadow-sm"
                                    :class="[
                                        colorClassForUnit(event.unit),
                                        collisionSignatures.has(eventKey(event)) ? 'ring-2 ring-rose-400' : '',
                                    ]"
                                    :style="{
                                        top: eventTop(event),
                                        height: eventHeight(event),
                                    }"
                                >
                                    <div class="font-semibold">{{ event.unit }} {{ normalizeClassType(event.classType) }} {{ event.optionCode }}</div>
                                    <div class="opacity-80">{{ event.start }}-{{ event.end }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold">Horários já feitos</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <input v-model="filters.search" type="text" placeholder="Pesquisar combinacao" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                    <input v-model.number="filters.maxDays" type="number" min="1" max="6" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                    <input v-model.number="filters.maxGaps" type="number" min="0" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" />
                    <select v-model="filters.sortBy" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                        <option value="score">Ordenar por score</option>
                        <option value="days">Menos dias</option>
                        <option value="gaps">Menos buracos</option>
                        <option value="earliest">Inicio mais tarde</option>
                    </select>
                </div>

                <div class="mt-4 flex flex-col gap-2">
                    <details v-for="schedule in filteredSchedules.slice(0, 20)" :key="schedule.key" class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <summary class="cursor-pointer list-none">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                <strong>{{ schedule.key }}</strong>
                                <span class="text-slate-600">Score {{ schedule.metrics.score }} | Dias {{ schedule.metrics.daysCount }} | Buracos {{ schedule.metrics.gaps }}m</span>
                            </div>
                        </summary>
                        <div class="mt-3 flex flex-col gap-3 border-t border-slate-200 pt-3">
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="event in schedule.events"
                                    :key="`${schedule.key}-${event.unit}-${event.classType}-${event.optionCode}-${event.day}-${event.start}`"
                                    class="rounded-full border border-slate-300 bg-white px-2 py-1 text-[11px] text-slate-700"
                                >
                                    {{ event.unit }} {{ normalizeClassType(event.classType) }} {{ event.optionCode }} · {{ event.day }} {{ event.start }}-{{ event.end }}
                                </span>
                            </div>
                            <div>
                                <button
                                    type="button"
                                    class="rounded-lg border border-blue-300 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-800 hover:bg-blue-100"
                                    @click="applyScheduleToBuilder(schedule)"
                                >
                                    Aplicar este horário no construtor
                                </button>
                            </div>
                        </div>
                    </details>
                </div>
            </section>
        </div>
    </main>
</template>
