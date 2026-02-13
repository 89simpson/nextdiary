<template>
	<div id="nextdiary-settings-page">
		<h2>{{ t('nextdiary', 'Diary settings') }}</h2>
		<div class="settings-list">
			<div v-for="(sectionKey, index) in localSettings.sidebar_order"
				:key="sectionKey"
				class="settings-row">
				<NcCheckboxRadioSwitch :checked.sync="localSettings[showKey(sectionKey)]"
					@update:checked="updateSetting(showKey(sectionKey), $event)">
					{{ sectionLabel(sectionKey) }}
				</NcCheckboxRadioSwitch>
				<div class="order-buttons">
					<NcButton type="tertiary"
						:disabled="index === 0"
						:aria-label="t('nextdiary', 'Move up')"
						@click="moveSection(index, -1)">
						<template #icon>
							<ArrowUp :size="20" />
						</template>
					</NcButton>
					<NcButton type="tertiary"
						:disabled="index === localSettings.sidebar_order.length - 1"
						:aria-label="t('nextdiary', 'Move down')"
						@click="moveSection(index, 1)">
						<template #icon>
							<ArrowDown :size="20" />
						</template>
					</NcButton>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch, NcButton } from '@nextcloud/vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp'
import ArrowDown from 'vue-material-design-icons/ArrowDown'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'SettingsPage',
	components: {
		NcCheckboxRadioSwitch,
		NcButton,
		ArrowUp,
		ArrowDown,
	},
	data() {
		return {
			localSettings: {
				show_mood: true,
				show_wellbeing: true,
				show_tags: true,
				show_symptoms: true,
				show_medications: true,
				sidebar_order: ['mood', 'wellbeing', 'tags', 'symptoms', 'medications'],
			},
		}
	},
	mounted() {
		this.fetchSettings()
	},
	methods: {
		showKey(sectionKey) {
			return 'show_' + sectionKey
		},
		sectionLabel(key) {
			const labels = {
				mood: t('nextdiary', 'Mood'),
				wellbeing: t('nextdiary', 'Wellbeing'),
				tags: t('nextdiary', 'Tags'),
				symptoms: t('nextdiary', 'Symptoms'),
				medications: t('nextdiary', 'Medications'),
			}
			return labels[key] || key
		},
		async fetchSettings() {
			try {
				const response = await axios.get(generateUrl('/apps/nextdiary/api/settings'))
				if (response.data) {
					this.localSettings = { ...this.localSettings, ...response.data }
				}
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('[NextDiary] Error fetching settings:', error)
			}
		},
		async updateSetting(key, value) {
			try {
				await axios.put(generateUrl('/apps/nextdiary/api/settings'), {
					key,
					value,
				})
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('[NextDiary] Error updating setting:', error)
			}
		},
		moveSection(index, direction) {
			const newIndex = index + direction
			const order = [...this.localSettings.sidebar_order]
			const temp = order[index]
			order[index] = order[newIndex]
			order[newIndex] = temp
			this.localSettings.sidebar_order = order
			this.updateSetting('sidebar_order', order)
		},
	},
}
</script>

<style lang="scss" scoped>
#nextdiary-settings-page {
	padding: 20px;

	h2 {
		font-size: 20px;
		font-weight: 700;
		margin-bottom: 20px;
	}

	.settings-list {
		display: flex;
		flex-direction: column;
		gap: 4px;
	}

	.settings-row {
		display: flex;
		align-items: center;
		gap: 8px;

		.order-buttons {
			display: flex;
			gap: 0;
			margin-left: auto;
		}
	}
}
</style>
