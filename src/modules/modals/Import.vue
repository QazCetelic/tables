<template>
	<NcModal v-if="showModal" size="normal" @close="actionCancel">
		<div class="modal__content">
			<div class="row">
				<div class="col-4">
					<h2>{{ t('tables', 'Import') }}</h2>
				</div>
			</div>

			<!-- Starting -->
			<div v-if="!loading && result === null && !waitForReload">
				<RowFormWrapper :title="t('tables', 'File')" :description="t('tables', 'Supported formats are xlsx, xls, html, xml and csv.')">
					<div class="fix-col-4 space-T-small middle">
						<NcButton :aria-label="t('tables', 'Select a file')" @click="pickFile">
							<template #icon>
								<IconFolder :size="20" />
							</template>
							{{ t('tables', 'Select a file') }}
						</NcButton>
						<input v-model="path" :class="{ missing: pathError }">
					</div>
				</RowFormWrapper>

				<RowFormWrapper :title="t('tables', 'Create missing columns')" :description="t('tables', 'Columns are identified by the titles. If there is no match, a new text-line column will be created.')">
					<div class="fix-col-2">
						<NcCheckboxRadioSwitch :checked.sync="createMissingColumns" type="switch" :disabled="!canCreateMissingColumns">
							{{ t('tables', 'Create missing columns') }}
						</NcCheckboxRadioSwitch>
					</div>
					<p v-if="(isElementView && !canManageTable(element)) || !canManageElement(element)" class="fix-col-2 span">
						{{ t('tables', '⚠️ You don\'t have the permission to create columns.') }}
					</p>
				</RowFormWrapper>

				<div class="information">
					<RowFormWrapper :title="t('tables', 'Information')">
						<div class="fix-col-4">
							<p class="span">
								{{ t('tables', 'The first row has to contain the column titles.') }}
							</p>
						</div>
						<div class="fix-col-4">
							<p class="span">
								{{ t('tables', 'Note that imported data will be added to the table. Updating of existing rows is not possible at the moment.') }}
							</p>
						</div>
						<div class="fix-col-4">
							<p class="span">
								{{ t('tables', 'The possible importing size depends on the system configuration and is only limited by execution time and memory.') }}
							</p>
						</div>
					</RowFormWrapper>
				</div>

				<div class="row">
					<div class="fix-col-4 end">
						<NcButton :aria-label="t('tables', 'Import')" type="primary" @click="actionSubmit">
							{{ t('tables', 'Import') }}
						</NcButton>
					</div>
				</div>
			</div>

			<!-- show results -->
			<div v-if="!loading && result !== null && !waitForReload">
				<RowFormWrapper v-if="result !== ''" :title="t('tables', 'Result')">
					<div class="fix-col-1">
						{{ t('tables', 'Found columns') }}
					</div>
					<div class="fix-col-3">
						{{ result['found_columns_count'] }}
					</div>
					<div class="fix-col-1">
						{{ t('tables', 'Matching columns') }}
					</div>
					<div class="fix-col-3">
						{{ result['matching_columns_count'] }}
					</div>
					<div class="fix-col-1">
						{{ t('tables', 'Created columns') }}
					</div>
					<div class="fix-col-3">
						{{ result['created_columns_count'] }}
					</div>
					<div class="fix-col-1">
						{{ t('tables', 'Inserted rows') }}
					</div>
					<div class="fix-col-3">
						{{ result['inserted_rows_count'] }}
					</div>
					<div class="fix-col-1">
						{{ t('tables', 'Value parsing errors') }}
					</div>
					<div class="fix-col-3">
						{{ result['errors_parsing_count'] }}
					</div>
					<div class="fix-col-1">
						{{ t('tables', 'Row creation errors') }}
					</div>
					<div class="fix-col-3">
						{{ result['errors_count'] }}
					</div>
				</RowFormWrapper>

				<RowFormWrapper v-else :title="t('tables', 'Result')">
					{{ t('tables', 'Error during importing. Please read the logs for more information.') }}
				</RowFormWrapper>

				<div class="row">
					<div class="fix-col-4 space-T end">
						<NcButton :aria-label="t('tables', 'Done')" type="primary" @click="actionCloseAndReload">
							{{ t('tables', 'Done') }}
						</NcButton>
					</div>
				</div>
			</div>

			<!-- show loading -->
			<div v-if="loading && !waitForReload">
				<NcEmptyContent :title="t('tables', 'Importing...')" :description="t('tables', 'Please wait while we try our best to import your data. This might take some time, depending on the server configuration.')">
					<template #icon>
						<NcIconTimerSand />
					</template>
				</NcEmptyContent>
			</div>

			<div v-if="waitForReload">
				<NcLoadingIcon :title="t('tables', 'Loading table data')" :size="64" />
			</div>
		</div>
	</NcModal>
</template>

<script>
import { NcModal, NcButton, NcCheckboxRadioSwitch, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { FilePicker, FilePickerType, showError, showWarning } from '@nextcloud/dialogs'
import RowFormWrapper from '../../shared/components/ncTable/partials/rowTypePartials/RowFormWrapper.vue'
import permissionsMixin from '../../shared/components/ncTable/mixins/permissionsMixin.js'
import IconFolder from 'vue-material-design-icons/Folder.vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { mapGetters } from 'vuex'
import NcIconTimerSand from '../../shared/components/ncIconTimerSand/NcIconTimerSand.vue'

export default {

	components: {
		NcIconTimerSand,
		NcLoadingIcon,
		IconFolder,
		NcModal,
		NcButton,
		NcCheckboxRadioSwitch,
		RowFormWrapper,
		NcEmptyContent,
	},

	mixins: [permissionsMixin],

	props: {
		showModal: {
			type: Boolean,
			default: false,
		},
		element: {
			type: Object,
			default: null,
		},
		isElementView: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			path: '',
			createMissingColumns: true,
			pathError: false,
			loading: false,
			result: null,
			waitForReload: false,
		}
	},

	computed: {
		...mapGetters(['activeElement', 'isView']),
		canCreateMissingColumns() {
			return this.isElementView ? this.canManageTable(this.element) : this.canManageElement(this.element)
		},
		getCreateMissingColumns() {
			return this.canCreateMissingColumns && this.createMissingColumns
		},
	},
	watch: {
		element() {
			if (this.element) {
				this.createMissingColumns = this.canCreateMissingColumns
			}
		},
	},

	methods: {
		async actionCloseAndReload() {
			// reload data if active element was affected
			if ((this.isView && this.isElementView && this.activeElement.tableId === this.element.tableId)
			|| (this.isView && !this.isElementView && this.activeElement.tableId === this.element.id)
			|| (!this.isView && this.isElementView && this.activeElement.id === this.element.tableId)
			|| (!this.isView && !this.isElementView && this.activeElement.id === this.element.id)) {
				this.waitForReload = true
				await this.$store.dispatch('loadTablesFromBE')
				await this.$store.dispatch('loadViewsSharedWithMeFromBE')
				await this.$store.dispatch('loadColumnsFromBE', {
					view: this.isElementView ? this.element : null,
					table: !this.isElementView ? this.element : null,
				})
				if (this.canReadData(this.element)) {
					await this.$store.dispatch('loadRowsFromBE', {
						viewId: this.isElementView ? this.element.id : null,
						tableId: !this.isElementView ? this.element.id : null,
					})
				}
				this.waitForReload = false
			}

			this.actionCancel()
		},
		actionSubmit() {
			if (this.path === '') {
				showWarning(t('tables', 'Please select a file.'))
				this.pathError = true
				return null
			}
			this.pathError = false
			this.import()
		},
		async import() {
			this.loading = true
			try {
				const res = await axios.post(generateUrl('/apps/tables/import/' + (this.isElementView ? 'view' : 'table') + '/' + this.element.id), { path: this.path, createMissingColumns: this.getCreateMissingColumns })
				if (res.status === 200) {
					this.result = res.data
					this.loading = false
				} else if (res.status === 401) {
					console.debug('error while importing', res)
					showError(t('tables', 'Could not import, not authorized. Are you logged in?'))
				} else if (res.status === 403) {
					console.debug('error while importing', res)
					showError(t('tables', 'Could not import, missing needed permission.'))
				} else if (res.status === 404) {
					console.debug('error while importing', res)
					showError(t('tables', 'Could not import, needed resources were not found.'))
				} else {
					showError(t('tables', 'Could not import data due to unknown errors.'))
					console.debug('error while importing', res)
				}
			} catch (e) {
				console.error(e)
				return false
			}
		},
		actionCancel() {
			this.reset()
			this.$emit('close')
		},
		reset() {
			this.path = ''
			this.pathError = false
			this.createMissingColumns = true
			this.result = null
			this.loading = false
		},
		pickFile() {
			const filePicker = new FilePicker(
				t('text', 'Select file for the import'),
				false, // multiselect
				[
					'text/csv',
					'application/vnd.ms-excel',
					'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
					'application/xml',
					'text/html',
					'application/vnd.oasis.opendocument.spreadsheet',
				], // mime filter
				true, // modal
				FilePickerType.Choose, // type
				false, // directories
				this.path // path
			)

			filePicker.pick().then((file) => {
				const client = OC.Files.getClient()
				client.getFileInfo(file).then((_status, fileInfo) => {
					this.path = fileInfo.path === '/' ? `/${fileInfo.name}` : `${fileInfo.path}/${fileInfo.name}`
				})
			})
		},
	},

}
</script>
<style lang="scss" scoped>

	h2 {
		margin-bottom: 0;
	}

	:deep(.slot), .middle {
		align-items: center;
	}

	.slot button {
		min-width: fit-content;
		margin-right: calc(var(--default-grid-baseline) * 3);
	}

	:deep(.empty-content p) {
		text-align: center;
	}

	:deep(.slot) {
		display: block;
	}

	.information :deep(.row.space-T) {
		padding-top: calc(var(--default-grid-baseline) * 2);
	}

</style>
