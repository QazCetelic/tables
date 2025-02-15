<template>
	<div>
		<h3>{{ t('tables', 'Shares') }}</h3>
		<div v-if="loading" class="icon-loading" />
		<ul v-if="getShares && getShares.length > 0" class="sharedWithList">
			<div v-for="share in getShares"
				:key="share.id"
				class="row">
				<div class="fix-col-2">
					<div style="display:flex; align-items: center;">
						<NcAvatar :user="share.receiver" :is-no-user="share.receiverType !== 'user'" />
					</div>
					<div class="userInfo">
						<div :class="{'high-line-height': !personHasTableManagePermission(share.receiver)}">
							{{ share.receiverDisplayName }}{{ share.receiverType === 'group' ? ' (' + t('tables', 'group') + ')' : '' }}
						</div>
						<div v-if="personHasTableManagePermission(share.receiver)">
							{{ '(' + t('tables', 'Table manager') + ')' }}
						</div>
					</div>
				</div>
				<div class="fix-col-2" style="justify-content: end;">
					<ShareInfoPopover v-if="debug" :share="share" />

					<NcActions :force-menu="true">
						<template v-if="personHasTableManagePermission(share.receiver)" #icon>
							<Crown :size="20" />
						</template>
						<template v-if="!personHasTableManagePermission(share.receiver)">
							<NcActionCaption :title="t('tables', 'Permissions')" />
							<NcActionCheckbox :checked.sync="share.permissionRead"
								:disabled="share.permissionManage || share.permissionUpdate || share.permissionDelete"
								@check="updatePermission(share, 'read', true)"
								@uncheck="updatePermission(share, 'read', false)">
								{{ t('tables', 'Read data') }}
							</NcActionCheckbox>
							<NcActionCheckbox :checked.sync="share.permissionCreate"
								:disabled="share.permissionManage"
								@check="updatePermission(share, 'create', true)"
								@uncheck="updatePermission(share, 'create', false)">
								{{ t('tables', 'Create data') }}
							</NcActionCheckbox>
							<NcActionCheckbox :checked.sync="share.permissionUpdate"
								:disabled="share.permissionManage"
								@check="updatePermission(share, 'update', true)"
								@uncheck="updatePermission(share, 'update', false)">
								{{ t('tables', 'Update data') }}
							</NcActionCheckbox>
							<NcActionCheckbox :checked.sync="share.permissionDelete"
								:disabled="share.permissionManage"
								@check="updatePermission(share, 'delete', true)"
								@uncheck="updatePermission(share, 'delete', false)">
								{{ t('tables', 'Delete data') }}
							</NcActionCheckbox>
							<NcActionCheckbox v-if="share.nodeType === 'view'"
								:checked.sync="share.permissionManage"
								@check="updatePermission(share, 'manage', true)"
								@uncheck="updatePermission(share, 'manage', false)">
								{{ t('tables', 'Manage view') }}
							</NcActionCheckbox>
							<NcActionButton v-if="!isView && !personHasTableManagePermission(share.receiver)"
								:close-after-click="true"
								@click="promoteToManager(share)">
								<template #icon>
									<Crown :size="20" />
								</template>
								{{ t('tables', 'Promote to table manager') }}
							</NcActionButton>
							<NcActionSeparator />
							<NcActionButton :close-after-click="true" icon="icon-delete" @click="actionDelete(share)">
								{{ t('tables', 'Delete') }}
							</NcActionButton>
							<NcActionText>
								<template #icon>
									<Information :size="20" />
								</template>
								{{ t('tables', 'Last edit') + ': ' }}{{ updateTime(share) }}
							</NcActionText>
						</template>
						<template v-else-if="!isView">
							<NcActionButton
								:close-after-click="true"
								@click="demoteManager(share)">
								<template #icon>
									<Account :size="20" />
								</template>
								{{ t('tables', 'Demote to normal share') }}
							</NcActionButton>
							<NcActionText>
								<template #icon>
									<Information :size="20" />
								</template>
								{{ t('tables', 'Last edit') + ': ' }}{{ updateTime(share) }}
							</NcActionText>
						</template>
						<template v-else>
							<NcActionButton
								:close-after-click="true"
								@click="openDashboard()">
								<template #icon>
									<OpenInNew :size="20" />
								</template>
								{{ t('tables', 'Open main table to adjust table management permissions') }}
							</NcActionButton>
						</template>
					</NcActions>
				</div>
			</div>
		</ul>
		<div v-else>
			{{ t('tables', 'No shares') }}
		</div>
	</div>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import formatting from '../../../shared/mixins/formatting.js'
import { NcActions, NcActionButton, NcAvatar, NcActionCheckbox, NcActionCaption, NcActionSeparator, NcActionText } from '@nextcloud/vue'
import ShareInfoPopover from './ShareInfoPopover.vue'
import OpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import Crown from 'vue-material-design-icons/Crown.vue'
import Information from 'vue-material-design-icons/Information.vue'
import Account from 'vue-material-design-icons/Account.vue'
import moment from '@nextcloud/moment'

export default {
	components: {
		NcAvatar,
		NcActionButton,
		NcActions,
		Information,
		Account,
		NcActionText,
		ShareInfoPopover,
		NcActionCheckbox,
		NcActionCaption,
		NcActionSeparator,
		OpenInNew,
		Crown,
	},

	mixins: [formatting],

	props: {
		shares: {
			type: Array,
			default: () => ([]),
		},
	},

	data() {
		return {
			loading: false,
			// To enable the share info popup
			debug: false,
		}
	},

	computed: {
		...mapState(['tables', 'tablesLoading', 'showSidebar']),
		...mapGetters(['activeElement', 'isView']),
		sortedShares() {
			return [...this.userShares, ...this.groupShares].slice()
				.sort(this.sortByDisplayName)
		},
		getShares() {
			if (this.isView) {
				return this.viewShares
			} else {
				return this.tableShares
			}
		},
		viewShares() {
			return this.shares.filter(share => share.nodeType === 'view')
			// .filter(share => this.tableShares.find(tableShare => tableShare.nodeId === this.activeElement.tableId && share.receiver === tableShare.receiver && tableShare.permissionManage) === undefined)
		},
		tableShares() {
			return this.shares.filter(share => share.nodeType === 'table')
		},
	},

	methods: {
		updateTime(share) {
			return (share && share.lastEditAt) ? this.relativeDateTime(share.lastEditAt) : ''
		},
		relativeDateTime(v) {
			return moment(v).format('L') === moment().format('L') ? t('tables', 'Today') + ' ' + moment(v).format('LT') : moment(v).format('LLLL')
		},
		async openDashboard() {
			await this.$router.push('/table/' + this.activeElement.tableId)
		},
		sortByDisplayName(a, b) {
			if (a.displayName.toLowerCase() < b.displayName.toLowerCase()) return -1
			if (a.displayName.toLowerCase() > b.displayName.toLowerCase()) return 1
			return 0
		},
		actionDelete(share) {
			this.$emit('remove', share)
		},
		updatePermission(share, permission, value) {
			this.$emit('update', { id: share.id, permission, value })
		},
		promoteToManager(share) {
			this.$emit('update', { id: share.id, permission: 'manage', value: true })
		},
		demoteManager(share) {
			this.$emit('update', { id: share.id, permission: 'manage', value: false })
		},
		personHasTableManagePermission(userId) {
			return this.tableShares.find(share => share.receiver === userId)?.permissionManage
		},
	},
}
</script>

<style lang="scss" scoped>

	.sharedWithList li {
		display: flex;
		justify-content: space-between;
		line-height: 44px;
	}

	.userInfo {
		padding-left: 5px;
		font-size: 100%;
		display: flex;
		flex-direction: column;
	}

	.high-line-height {
		line-height: 35px;
	}

	.manage-button {
		display: flex;
		justify-content: center;
	}

</style>
