<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

interface UsageEnvironment {
    name: string;
    status: string;
    php_major_version: string | null;
    uses_octane: boolean;
}

interface UsageApplication {
    name: string;
    cost_cents: number;
    burn_rate_cents_per_hour: number | null;
    deleted: boolean;
    region: string | null;
    repository: string | null;
    avatar_url: string | null;
    environments: UsageEnvironment[];
}

interface UsageResourceItem {
    name: string | null;
    cost_cents: number;
    type: string | null;
}

interface UsageResourceGroup {
    category: string;
    label: string;
    total_cents: number;
    items: UsageResourceItem[];
}

interface Usage {
    synced_at: string | null;
    cloud_updated_at: string | null;
    period: { offset: number; from: string | null; to: string | null };
    currency: string;
    summary: {
        current_spend_cents: number;
        resources_cost_cents: number;
        addons_cost_cents: number;
        applications_cost_cents: number;
        application_count: number;
        burn_rate_cents_per_hour: number | null;
        bandwidth: {
            cost_cents: number | null;
            usage_percentage: number | null;
            allowance_bytes: number | null;
        } | null;
        credits: {
            used_cents: number | null;
            total_cents: number | null;
        } | null;
        alert: {
            threshold_cents: number | null;
            remaining_percentage: number | null;
        } | null;
    };
    applications: UsageApplication[];
    resources: UsageResourceGroup[];
}

const props = defineProps<{ usage: Usage | null }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const currencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: props.usage?.currency ?? 'USD',
});

const preciseCurrencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: props.usage?.currency ?? 'USD',
    maximumFractionDigits: 4,
});

function money(cents: number | null): string {
    return currencyFormatter.format((cents ?? 0) / 100);
}

function burnRate(rate: number | null): string {
    if (rate === null) {
        return '—';
    }

    const dollars = rate / 100;
    const formatter =
        dollars !== 0 && Math.abs(dollars) < 0.01
            ? preciseCurrencyFormatter
            : currencyFormatter;

    return `${formatter.format(dollars)}/hr`;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatBytes(bytes: number | null): string {
    if (bytes === null) {
        return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let unit = 0;

    while (value >= 1000 && unit < units.length - 1) {
        value /= 1000;
        unit++;
    }

    return `${Number(value.toFixed(1))} ${units[unit]}`;
}

function percentOfApplications(cents: number): number {
    const total = props.usage?.summary.applications_cost_cents ?? 0;

    return total > 0 ? Math.round((cents / total) * 1000) / 10 : 0;
}

function applicationMeta(application: UsageApplication): string {
    return [application.repository, application.region]
        .filter(Boolean)
        .join(' · ');
}

const bandwidthBarClass = computed(() => {
    const percentage = props.usage?.summary.bandwidth?.usage_percentage ?? 0;

    if (percentage >= 90) {
        return 'bg-destructive';
    }

    return percentage >= 75 ? 'bg-amber-500' : 'bg-primary';
});

const periodLabel = computed(() => {
    const offset = props.usage?.period.offset ?? 0;

    if (offset === 0) {
        return 'Current billing period';
    }

    return offset === 1
        ? 'Previous billing period'
        : `${offset} billing periods ago`;
});

const spendSubline = computed(() => {
    if (!props.usage) {
        return '';
    }

    const parts: string[] = [];

    if (props.usage.summary.burn_rate_cents_per_hour !== null) {
        parts.push(
            `≈ ${burnRate(props.usage.summary.burn_rate_cents_per_hour)}`,
        );
    }

    if (props.usage.summary.alert) {
        parts.push(
            `Alert set at ${money(props.usage.summary.alert.threshold_cents)}`,
        );
    }

    return parts.length > 0 ? parts.join(' · ') : periodLabel.value;
});

const addonCount = computed(
    () =>
        props.usage?.resources.find((group) => group.category === 'addon')
            ?.items.length ?? 0,
);

const resourceItemCount = computed(
    () =>
        props.usage?.resources
            .filter(
                (group) => !['addon', 'environment'].includes(group.category),
            )
            .reduce((total, group) => total + group.items.length, 0) ?? 0,
);

const environmentStatusClasses: Record<string, string> = {
    running: 'bg-emerald-500',
    deploying: 'bg-blue-500',
    hibernating: 'bg-amber-500',
    stopped: 'bg-muted-foreground/40',
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div v-if="!usage" class="flex flex-1 items-center justify-center">
            <Card class="max-w-md text-center">
                <CardHeader>
                    <CardTitle>No usage data yet</CardTitle>
                    <CardDescription>
                        Pull your Laravel Cloud usage to see spend across
                        applications, environments, and resources.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <code
                        class="rounded-md bg-muted px-3 py-2 font-mono text-sm"
                        >php artisan cloud:sync</code
                    >
                </CardContent>
            </Card>
        </div>

        <template v-else>
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h1 class="text-xl font-semibold">Laravel Cloud usage</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ formatDate(usage.period.from) }} –
                        {{ formatDate(usage.period.to) }}
                    </p>
                </div>
                <p class="text-sm text-muted-foreground">
                    Last synced {{ formatDateTime(usage.synced_at) }}
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card class="gap-0 py-5">
                    <CardHeader>
                        <CardDescription>Current spend</CardDescription>
                        <CardTitle class="text-2xl tabular-nums">
                            {{ money(usage.summary.current_spend_cents) }}
                        </CardTitle>
                        <p class="text-xs text-muted-foreground">
                            {{ spendSubline }}
                        </p>
                    </CardHeader>
                </Card>

                <Card class="gap-0 py-5">
                    <CardHeader>
                        <CardDescription>Applications</CardDescription>
                        <CardTitle class="text-2xl tabular-nums">
                            {{ money(usage.summary.applications_cost_cents) }}
                        </CardTitle>
                        <p class="text-xs text-muted-foreground">
                            {{ usage.summary.application_count }}
                            application{{
                                usage.summary.application_count === 1 ? '' : 's'
                            }}
                        </p>
                    </CardHeader>
                </Card>

                <Card class="gap-0 py-5">
                    <CardHeader>
                        <CardDescription>Resources</CardDescription>
                        <CardTitle class="text-2xl tabular-nums">
                            {{ money(usage.summary.resources_cost_cents) }}
                        </CardTitle>
                        <p class="text-xs text-muted-foreground">
                            {{ resourceItemCount }} databases, caches, buckets &
                            sockets
                        </p>
                    </CardHeader>
                </Card>

                <Card class="gap-0 py-5">
                    <CardHeader>
                        <CardDescription>Add-ons</CardDescription>
                        <CardTitle class="text-2xl tabular-nums">
                            {{ money(usage.summary.addons_cost_cents) }}
                        </CardTitle>
                        <p class="text-xs text-muted-foreground">
                            {{ addonCount }} active add-on{{
                                addonCount === 1 ? '' : 's'
                            }}
                        </p>
                    </CardHeader>
                </Card>
            </div>

            <Card v-if="usage.summary.bandwidth" class="py-4">
                <CardContent class="flex flex-col gap-2">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 text-sm"
                    >
                        <span class="font-medium">Bandwidth</span>
                        <span class="text-muted-foreground">
                            {{ usage.summary.bandwidth.usage_percentage ?? 0 }}%
                            of
                            {{
                                formatBytes(
                                    usage.summary.bandwidth.allowance_bytes,
                                )
                            }}
                            allowance
                            <template v-if="usage.summary.bandwidth.cost_cents">
                                ·
                                {{ money(usage.summary.bandwidth.cost_cents) }}
                                overage
                            </template>
                        </span>
                    </div>
                    <div
                        class="h-2 w-full overflow-hidden rounded-full bg-muted"
                        role="meter"
                        :aria-valuenow="
                            usage.summary.bandwidth.usage_percentage ?? 0
                        "
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-label="Bandwidth allowance used"
                    >
                        <div
                            class="h-full rounded-full"
                            :class="bandwidthBarClass"
                            :style="{
                                width: `${Math.min(usage.summary.bandwidth.usage_percentage ?? 0, 100)}%`,
                            }"
                        />
                    </div>
                </CardContent>
            </Card>

            <div class="grid items-start gap-4 lg:grid-cols-3">
                <Card class="gap-0 overflow-hidden py-0 lg:col-span-2">
                    <CardHeader class="border-b pt-6">
                        <CardTitle>Applications</CardTitle>
                        <CardDescription>
                            Cost per application for this billing period
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="px-0 pb-2">
                        <div class="overflow-x-auto">
                            <table
                                class="w-full min-w-[540px] table-fixed text-sm"
                            >
                                <thead>
                                    <tr
                                        class="border-b text-left text-xs text-muted-foreground"
                                    >
                                        <th class="py-3 pr-3 pl-6 font-medium">
                                            Application
                                        </th>
                                        <th
                                            class="w-[30%] px-3 py-3 font-medium"
                                        >
                                            Environments
                                        </th>
                                        <th
                                            class="w-40 py-3 pr-6 pl-3 text-right font-medium"
                                        >
                                            Cost
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="application in usage.applications"
                                        :key="application.name"
                                        class="border-b last:border-0"
                                    >
                                        <td class="py-3 pr-3 pl-6">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <img
                                                    v-if="
                                                        application.avatar_url
                                                    "
                                                    :src="
                                                        application.avatar_url
                                                    "
                                                    alt=""
                                                    class="size-8 shrink-0 rounded-md border"
                                                />
                                                <div
                                                    v-else
                                                    class="flex size-8 shrink-0 items-center justify-center rounded-md border bg-muted text-xs font-medium uppercase"
                                                >
                                                    {{
                                                        application.name.charAt(
                                                            0,
                                                        )
                                                    }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div
                                                        class="flex items-center gap-2 font-medium"
                                                    >
                                                        {{ application.name }}
                                                        <Badge
                                                            v-if="
                                                                application.deleted
                                                            "
                                                            variant="outline"
                                                            class="border-destructive/40 text-destructive"
                                                        >
                                                            Deleted
                                                        </Badge>
                                                    </div>
                                                    <div
                                                        v-if="
                                                            applicationMeta(
                                                                application,
                                                            )
                                                        "
                                                        class="truncate text-xs text-muted-foreground"
                                                    >
                                                        {{
                                                            applicationMeta(
                                                                application,
                                                            )
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="flex flex-wrap gap-1.5">
                                                <Badge
                                                    v-for="environment in application.environments"
                                                    :key="environment.name"
                                                    variant="secondary"
                                                    class="gap-1.5 font-normal"
                                                    :title="`${environment.name} is ${environment.status}`"
                                                >
                                                    <span
                                                        class="size-1.5 rounded-full"
                                                        :class="
                                                            environmentStatusClasses[
                                                                environment
                                                                    .status
                                                            ] ??
                                                            'bg-muted-foreground/40'
                                                        "
                                                    />
                                                    {{ environment.name }}
                                                    <span class="sr-only">
                                                        ({{
                                                            environment.status
                                                        }})
                                                    </span>
                                                </Badge>
                                                <span
                                                    v-if="
                                                        application.environments
                                                            .length === 0
                                                    "
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    —
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-3 pr-6 pl-3">
                                            <div
                                                class="flex flex-col items-end gap-1"
                                            >
                                                <span
                                                    class="font-medium tabular-nums"
                                                >
                                                    {{
                                                        money(
                                                            application.cost_cents,
                                                        )
                                                    }}
                                                </span>
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <div
                                                        class="h-1.5 w-16 overflow-hidden rounded-full bg-muted"
                                                    >
                                                        <div
                                                            class="h-full rounded-full bg-primary"
                                                            :style="{
                                                                width: `${Math.min(percentOfApplications(application.cost_cents), 100)}%`,
                                                            }"
                                                        />
                                                    </div>
                                                    <span
                                                        class="w-10 text-right text-xs text-muted-foreground tabular-nums"
                                                    >
                                                        {{
                                                            percentOfApplications(
                                                                application.cost_cents,
                                                            )
                                                        }}%
                                                    </span>
                                                </div>
                                                <span
                                                    v-if="
                                                        application.burn_rate_cents_per_hour !==
                                                        null
                                                    "
                                                    class="text-xs text-muted-foreground tabular-nums"
                                                >
                                                    ≈
                                                    {{
                                                        burnRate(
                                                            application.burn_rate_cents_per_hour,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex flex-col gap-4">
                    <Card
                        v-for="group in usage.resources"
                        :key="group.category"
                        class="gap-0 overflow-hidden py-0"
                    >
                        <CardHeader class="border-b pt-6">
                            <CardTitle class="text-base">
                                {{ group.label }}
                            </CardTitle>
                            <CardAction
                                class="text-sm font-medium tabular-nums"
                            >
                                {{ money(group.total_cents) }}
                            </CardAction>
                        </CardHeader>
                        <CardContent class="px-0 pb-2">
                            <ul class="divide-y">
                                <li
                                    v-for="(item, index) in group.items"
                                    :key="index"
                                    class="flex items-center justify-between gap-3 px-6 py-2.5 text-sm"
                                >
                                    <div class="min-w-0">
                                        <div class="truncate">
                                            {{ item.name ?? 'Unnamed' }}
                                        </div>
                                        <div
                                            v-if="item.type"
                                            class="truncate text-xs text-muted-foreground"
                                        >
                                            {{ item.type }}
                                        </div>
                                    </div>
                                    <span
                                        class="shrink-0 text-muted-foreground tabular-nums"
                                    >
                                        {{ money(item.cost_cents) }}
                                    </span>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </template>
    </div>
</template>
