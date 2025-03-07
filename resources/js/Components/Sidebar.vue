<template>
    <div class="flex flex-col w-72 px-3 py-10 pt-2 overflow-y-auto font-semibold text-white bg-gray-900v mobile:w-full mobile:py-4 sidebar" :class="{ 'w-10': collapsed }">
        <!-- General stuff -->
        <div class="pb-3 flex justify-between items-center gap-3" :class="{ '!justify-center': collapsed }">
            <div class="relative w-full" v-if="!collapsed">
                <input v-model="search" type="text" :placeholder="t('global.search_placeholder')" class="px-3 py-1 w-full mr-2 bg-gray-900 border-none rounded" :class="{ 'pr-7': search }" />

                <i class="fas fa-times absolute text-gray-300 top-1/2 right-2.5 transform -translate-y-1/2 cursor-pointer" v-if="search" @click="search = ''"></i>
            </div>

            <a href="#" @click="collapse">
                <i class="fas fa-expand-alt" v-if="collapsed"></i>
                <i class="fas fa-compress-alt" v-else></i>
            </a>
        </div>

        <nav v-if="!collapsed">
            <ul v-if="!isMobile()">
                <li v-for="link in links" :key="link.label" v-if="(!link.private || $page.auth.player.isSuperAdmin) && !link.hidden">
                    <inertia-link class="flex items-center px-5 py-2 mb-2 rounded hover:bg-gray-900 hover:text-white whitespace-nowrap drop-shadow" :class="isUrl(link.url) ? ['bg-gray-900', 'text-white'] : ''" :href="link.url" v-if="!('sub' in link) && matchesSearch(link)">
                        <icon class="w-4 h-4 mr-3 fill-current" :name="link.icon"></icon>
                        {{ getLinkLabel(link) }}
                    </inertia-link>
                    <a href="#" class="flex flex-wrap items-center px-5 py-2 mb-2 -mt-1 rounded hover:bg-gray-700v hover:text-white overflow-hidden" :class="height(link.sub, $page.auth.player.isSuperAdmin)" v-if="link.sub && height(link.sub, $page.auth.player.isSuperAdmin)" @click="$event.preventDefault()">
                        <span class="block w-full mb-2 whitespace-nowrap drop-shadow">
                            <icon class="w-4 h-4 mr-3 fill-current" :name="link.icon"></icon>
                            {{ getLinkLabel(link) }}
                        </span>
                        <ul class="w-full">
                            <li v-for="sub in link.sub" :key="sub.label" v-if="(!sub.private || $page.auth.player.isSuperAdmin) && !sub.hidden && !link.hidden && matchesSearch(sub)">
                                <inertia-link class="flex items-center px-5 py-2 mt-1 rounded hover:bg-gray-900 hover:text-white whitespace-nowrap drop-shadow" :class="isUrl(sub.url) ? ['bg-gray-900', 'text-white'] : ''" :href="sub.url">
                                    <icon class="w-4 h-4 mr-3 fill-current" :name="sub.icon"></icon>
                                    {{ getLinkLabel(sub) }}
                                </inertia-link>
                            </li>
                        </ul>
                    </a>
                </li>
            </ul>

            <ul v-else class="mobile:flex mobile:flex-wrap mobile:justify-between">
                <template v-for="link in links">
                    <inertia-link class="flex items-center px-5 py-2 mb-2 rounded hover:bg-gray-900 hover:text-white text-sm drop-shadow" :class="isUrl(link.url) ? ['bg-gray-900', 'text-white'] : ''" :href="link.url" v-if="!('sub' in link) && (!link.private || $page.auth.player.isSuperAdmin) && !link.hidden && matchesSearch(link)">
                        {{ getLinkLabel(link) }}
                    </inertia-link>
                    <inertia-link v-for="sub in link.sub" class="flex items-center px-5 py-2 mb-2 rounded hover:bg-gray-900 hover:text-white text-sm drop-shadow" :class="isUrl(sub.url) ? ['bg-gray-900', 'text-white'] : ''" :href="sub.url" :key="sub.label" v-if="'sub' in link && (!(sub.private || link.private) || $page.auth.player.isSuperAdmin) && !(sub.hidden || link.hidden) && matchesSearch(sub)">
                        {{ getLinkLabel(sub) }}
                    </inertia-link>
                </template>
            </ul>
        </nav>

        <div class="mt-auto">
            <!-- Update available -->
            <a class="block px-5 py-2 mt-3 text-center text-black bg-green-400 rounded" target="_blank" href="https://github.com/coalaura/opfw-admin" v-if="!isMobile() && !collapsed && $page.update && $page.auth.player.isSuperAdmin">
                <i class="mr-2 fas fa-wrench"></i> {{ t("nav.update") }}
            </a>

            <!-- Suggest a feature -->
            <a class="block px-5 py-2 mt-3 text-center text-black bg-yellow-400 rounded" target="_blank" href="https://github.com/coalaura/opfw-admin/issues/new/choose" v-if="!isMobile() && !collapsed">
                <i class="mr-2 fas fa-bug"></i> {{ t("nav.report") }}
            </a>
        </div>

        <div class="fixed left-3 bottom-2 text-sm">{{ time }}</div>
    </div>
</template>

<script>
import Icon from './Icon.vue';

export default {
    components: {
        Icon,
    },
    data() {
        let links = [
            {
                label: 'home.title',
                icon: 'dashboard',
                url: '/',
            },
            {
                label: 'sidebar.lookup',
                icon: 'glasses',
                sub: [
                    {
                        label: 'steam.title',
                        icon: 'steam',
                        url: '/steam',
                    },
                    {
                        label: 'discord.title',
                        icon: 'discord',
                        url: '/discord',
                    }
                ]
            },
            {
                label: 'sidebar.community',
                icon: 'users',
                sub: [
                    {
                        label: 'players.title',
                        icon: 'user',
                        url: '/players',
                    },
                    {
                        label: 'players.new.title',
                        icon: 'kiwi',
                        url: '/new_players',
                    },
                    {
                        label: 'characters.title',
                        icon: 'book',
                        url: '/characters',
                    },
                    {
                        label: 'stocks.title',
                        icon: 'home',
                        url: '/stocks/companies',
                    },
                    {
                        label: 'containers.title',
                        icon: 'warehouse',
                        url: '/containers',
                    },
                    {
                        label: 'twitter.title',
                        icon: 'twitter',
                        url: '/twitter',
                    },
                    {
                        label: 'map.title',
                        icon: 'map',
                        url: '/map',
                        hidden: !this.perm.check(this.perm.PERM_LIVEMAP) && !this.$page.auth.player.isDebugger,
                    }
                ]
            },
            {
                label: 'sidebar.logs',
                icon: 'boxes',
                sub: [
                    {
                        label: 'logs.title',
                        icon: 'printer',
                        url: '/logs',
                    },
                    {
                        label: 'logs.damage',
                        icon: 'medkit',
                        url: '/damage',
                        hidden: !this.perm.check(this.perm.PERM_DAMAGE_LOGS),
                    },
                    {
                        label: 'logs.money_title',
                        icon: 'money',
                        url: '/moneyLogs',
                        hidden: !this.perm.check(this.perm.PERM_MONEY_LOGS),
                    },
                    {
                        label: 'phone.title',
                        icon: 'phone',
                        url: '/phoneLogs',
                        hidden: !this.perm.check(this.perm.PERM_PHONE_LOGS),
                    },
                    {
                        label: 'logs.dark_chat',
                        icon: 'mail',
                        url: '/darkChat',
                        hidden: !this.perm.check(this.perm.PERM_DARK_CHAT),
                    },
                    {
                        label: 'casino.title',
                        icon: 'chess',
                        url: '/casino',
                    },
                    {
                        label: 'panel_logs.title',
                        icon: 'spell-check',
                        url: '/panel',
                    },
                    {
                        label: 'search_logs.title',
                        icon: 'binoculars',
                        url: '/searches',
                        hidden: !this.perm.check(this.perm.PERM_ADVANCED),
                    },
                    {
                        label: 'screenshot_logs.title',
                        icon: 'portrait',
                        url: '/screenshot_logs',
                        hidden: !this.perm.check(this.perm.PERM_ADVANCED),
                    }
                ]
            },
            {
                label: 'sidebar.bans',
                icon: 'user-slash',
                sub: [
                    {
                        label: 'sidebar.all_bans',
                        icon: 'friends',
                        url: '/bans',
                    },
                    {
                        label: 'sidebar.my_bans',
                        icon: 'user',
                        url: '/my_bans',
                    },
                    {
                        label: 'sidebar.system_bans',
                        icon: 'kiwi',
                        url: '/system_bans',
                    }
                ]
            },
            {
                label: 'sidebar.administration',
                icon: 'tasks',
                sub: [
                    {
                        label: 'tokens.title',
                        icon: 'key',
                        hidden: !this.perm.check(this.perm.PERM_API_TOKENS),
                        url: '/tokens',
                    },
                    {
                        label: 'roles.title',
                        icon: 'user-md',
                        url: '/roles',
                    },
                    {
                        label: 'blacklist.title',
                        icon: 'shield',
                        private: true,
                        url: '/blacklist',
                    },
                    {
                        label: 'loading_screen.sidebar',
                        icon: 'spinner',
                        hidden: !this.perm.check(this.perm.PERM_LOADING_SCREEN),
                        url: '/loading_screen',
                    },
                    {
                        label: 'screenshot.anti_cheat_title',
                        icon: 'ghost',
                        url: '/anti_cheat',
                        hidden: !this.perm.check(this.perm.PERM_ANTI_CHEAT),
                    }
                ]
            },
            {
                label: 'sidebar.data_stats',
                icon: 'server',
                sub: [
                    {
                        label: 'statistics.title',
                        icon: 'statistics',
                        url: '/statistics',
                    },
                    {
                        label: 'points.title',
                        icon: 'street-view',
                        url: '/points',
                    },
                    {
                        label: 'staff_statistics.title',
                        icon: 'laptop-medical',
                        url: '/staff',
                    }
                ]
            },
            {
                label: 'sidebar.tools',
                icon: 'tools',
                sub: [
                    {
                        label: 'sidebar.overwatch',
                        icon: 'camera',
                        url: '/overwatch',
                        hidden: !this.perm.check(this.perm.PERM_SCREENSHOT),
                    },
                    {
                        label: 'overwatch.live',
                        icon: 'video',
                        url: '/live',
                        hidden: !this.$page.overwatch,
                    },
                    {
                        label: 'backstories.title',
                        icon: 'box-open',
                        url: '/backstories',
                    },
                    {
                        label: 'weapons.title',
                        icon: 'damage',
                        url: '/weapons',
                        hidden: !this.perm.check(this.perm.PERM_ADVANCED),
                    },
                    {
                        label: 'vehicles.title',
                        icon: 'crash',
                        url: '/vehicles',
                    },
                    {
                        label: 'tools.config.title',
                        icon: 'cogs',
                        url: '/tools/config'
                    }
                ]
            },
            {
                label: 'sidebar.advanced',
                icon: 'cogs',
                sub: [
                    {
                        label: 'sidebar.advanced_search',
                        icon: 'search',
                        url: '/advanced',
                        hidden: !this.perm.check(this.perm.PERM_ADVANCED),
                    },
                    {
                        label: 'sidebar.suspicious',
                        icon: 'heart',
                        url: '/suspicious',
                        hidden: !this.perm.check(this.perm.PERM_SUSPICIOUS),
                    }
                ]
            },
            {
                label: 'sidebar.errors',
                icon: 'bug',
                hidden: !this.$page.auth.player.isSuperAdmin,
                sub: [
                    {
                        label: 'errors.client.title',
                        icon: 'spider',
                        url: '/errors/client?server_version=newest',
                    },
                    {
                        label: 'errors.server.title',
                        icon: 'virus',
                        url: '/errors/server?server_version=newest'
                    }
                ]
            }
        ]

        if (this.setting('expandSidenav')) {
            links = links.reduce((acc, val) => acc.concat(val.sub ? val.sub : val).map(v => ({
                hidden: val.hidden,
                ...v
            })), []);
        }

        return {
            url: this.$page.url,
            links: links,
            collapsed: false,
            search: "",
            heights: {},
            interval: false,
            time: dayjs().format("h:mm A"),
        };
    },
    watch: {
        '$page.url': function (url) {
            this.url = url;
        }
    },
    methods: {
        isUrl(url) {
            const test = url.replace(/[?#].+$/m, ""),
                against = this.url.replace(/[?#].+$/m, "");

            return test === against;
        },
        height(sub, isSuperAdmin) {
            const length = sub.filter(l => (!l.private || isSuperAdmin) && !l.hidden && this.matchesSearch(l)).length;

            if (length === 0) return 'hidden';

            return `side-item side-${length}`;
        },
        isMobile() {
            return window.outerWidth <= 640;
        },
        collapse($event) {
            $event.preventDefault();

            this.collapsed = !this.collapsed;
        },
        getLinkLabel(link) {
            return link.raw ? link.raw : this.t(link.label);
        },
        matchesSearch(link) {
            const query = this.search.trim().toLowerCase();

            if (!query) return true;

            return this.getLinkLabel(link).toLowerCase().includes(query);
        }
    },
    mounted() {
        setInterval(() => {
            this.time = dayjs().format("h:mm A");
        }, 10000);
    },
    beforeMount() {
        let max = 0;

        for (const link of this.links) {
            if (!link.sub) continue;

            const length = link.sub.filter(l => (!l.private || this.$page.auth.player.isSuperAdmin) && !l.hidden).length;

            if (length > max) {
                max = length;
            }
        }

        const styles = document.createElement("style");

        // Closed
        styles.innerHTML += ".side-item { height: 37px; transition: height 0.3s ease; will-change: height; }";

        for (let i = 1; i <= max; i++) {
            // 37px = height of closed sidebar item
            let height = 37;

            // Each entry is 37.5px
            height += i * 37.5;

            // plus 0.25rem margin top (for each entry)
            height += (0.25 * 16) * i;

            // and 0.5rem padding bottom
            height += 0.5 * 16;

            styles.innerHTML += `.side-${i}:hover { height: ${height}px; }`;
        }

        document.head.appendChild(styles);
    }
};
</script>
