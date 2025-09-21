<template>
  <div class="task-calendar">
    <v-card variant="outlined">
      <v-card-title class="d-flex align-center justify-space-between">
        <span>Task Calendar</span>
        <div class="d-flex align-center gap-2">
          <v-btn-toggle v-model="viewMode" mandatory>
            <v-btn value="month" size="small">Month</v-btn>
            <v-btn value="week" size="small">Week</v-btn>
          </v-btn-toggle>
        </div>
      </v-card-title>
      
      <v-card-text>
        <div v-if="loading" class="text-center py-8">
          <v-progress-circular indeterminate color="primary" />
        </div>
        
        <div v-else class="calendar-container">
          <!-- Calendar Header -->
          <div class="d-flex align-center justify-space-between mb-4">
            <div class="d-flex align-center">
              <v-btn
                icon="mdi-chevron-left"
                variant="text"
                @click="previousPeriod"
              />
              <h3 class="text-h6 mx-4">{{ currentPeriodTitle }}</h3>
              <v-btn
                icon="mdi-chevron-right"
                variant="text"
                @click="nextPeriod"
              />
            </div>
            
            <v-btn
              color="primary"
              variant="outlined"
              size="small"
              @click="goToToday"
            >
              Today
            </v-btn>
          </div>
          
          <!-- Calendar Grid -->
          <div class="calendar-grid">
            <!-- Days of week header -->
            <div class="calendar-header">
              <div
                v-for="day in daysOfWeek"
                :key="day"
                class="calendar-day-header"
              >
                {{ day }}
              </div>
            </div>
            
            <!-- Calendar days -->
            <div class="calendar-body">
              <div
                v-for="date in calendarDates"
                :key="date.date"
                :class="[
                  'calendar-day',
                  {
                    'calendar-day--other-month': !date.isCurrentMonth,
                    'calendar-day--today': date.isToday,
                    'calendar-day--has-tasks': date.tasks.length > 0
                  }
                ]"
                @click="$emit('date-clicked', date.date)"
              >
                <div class="calendar-day-number">{{ date.day }}</div>
                
                <!-- Task indicators -->
                <div class="calendar-day-tasks">
                  <div
                    v-for="task in date.tasks.slice(0, 3)"
                    :key="task.id"
                    :class="[
                      'task-indicator',
                      `task-indicator--${task.priority}`
                    ]"
                    :title="task.title"
                    @click.stop="$emit('task-clicked', task)"
                  >
                    <span class="task-indicator-text">{{ task.title }}</span>
                  </div>
                  
                  <div
                    v-if="date.tasks.length > 3"
                    class="task-indicator task-indicator--more"
                    :title="`${date.tasks.length - 3} more tasks`"
                  >
                    +{{ date.tasks.length - 3 }} more
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  tasks: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['task-clicked', 'date-clicked'])

const viewMode = ref('month')
const currentDate = ref(new Date())

const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const currentPeriodTitle = computed(() => {
  if (viewMode.value === 'month') {
    return currentDate.value.toLocaleDateString('en-US', {
      month: 'long',
      year: 'numeric'
    })
  } else {
    // Week view
    const startOfWeek = new Date(currentDate.value)
    startOfWeek.setDate(currentDate.value.getDate() - currentDate.value.getDay())
    const endOfWeek = new Date(startOfWeek)
    endOfWeek.setDate(startOfWeek.getDate() + 6)
    
    return `${startOfWeek.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${endOfWeek.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`
  }
})

const calendarDates = computed(() => {
  const dates = []
  const today = new Date()
  
  if (viewMode.value === 'month') {
    // Month view
    const year = currentDate.value.getFullYear()
    const month = currentDate.value.getMonth()
    
    // First day of the month
    const firstDay = new Date(year, month, 1)
    // Last day of the month
    const lastDay = new Date(year, month + 1, 0)
    
    // Start from the first Sunday of the calendar
    const startDate = new Date(firstDay)
    startDate.setDate(firstDay.getDate() - firstDay.getDay())
    
    // Generate 42 days (6 weeks)
    for (let i = 0; i < 42; i++) {
      const date = new Date(startDate)
      date.setDate(startDate.getDate() + i)
      
      const dateString = date.toISOString().split('T')[0]
      const tasksForDate = props.tasks.filter(task => {
        return task.due_date && task.due_date.startsWith(dateString)
      })
      
      dates.push({
        date: dateString,
        day: date.getDate(),
        isCurrentMonth: date.getMonth() === month,
        isToday: date.toDateString() === today.toDateString(),
        tasks: tasksForDate
      })
    }
  }
  
  return dates
})

const previousPeriod = () => {
  if (viewMode.value === 'month') {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1)
  } else {
    currentDate.value = new Date(currentDate.value.getTime() - 7 * 24 * 60 * 60 * 1000)
  }
}

const nextPeriod = () => {
  if (viewMode.value === 'month') {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1)
  } else {
    currentDate.value = new Date(currentDate.value.getTime() + 7 * 24 * 60 * 60 * 1000)
  }
}

const goToToday = () => {
  currentDate.value = new Date()
}
</script>

<style scoped>
.calendar-container {
  width: 100%;
}

.calendar-grid {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
  overflow: hidden;
}

.calendar-header {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  background-color: rgba(var(--v-theme-surface-variant), 0.5);
}

.calendar-day-header {
  padding: 12px 8px;
  text-align: center;
  font-weight: 500;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.calendar-day-header:last-child {
  border-right: none;
}

.calendar-body {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  grid-template-rows: repeat(6, 1fr);
}

.calendar-day {
  min-height: 100px;
  padding: 8px;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.calendar-day:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
}

.calendar-day:nth-child(7n) {
  border-right: none;
}

.calendar-day--other-month {
  color: rgba(var(--v-theme-on-surface), 0.38);
  background-color: rgba(var(--v-theme-surface-variant), 0.2);
}

.calendar-day--today {
  background-color: rgba(var(--v-theme-primary), 0.08);
}

.calendar-day--today .calendar-day-number {
  background-color: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
  border-radius: 50%;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 500;
}

.calendar-day-number {
  font-weight: 500;
  margin-bottom: 4px;
}

.calendar-day-tasks {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.task-indicator {
  padding: 2px 6px;
  border-radius: 3px;
  font-size: 11px;
  line-height: 1.2;
  cursor: pointer;
  transition: all 0.2s ease;
}

.task-indicator:hover {
  transform: scale(1.02);
}

.task-indicator--low {
  background-color: rgba(var(--v-theme-success), 0.2);
  color: rgb(var(--v-theme-success));
}

.task-indicator--medium {
  background-color: rgba(var(--v-theme-warning), 0.2);
  color: rgb(var(--v-theme-warning));
}

.task-indicator--high,
.task-indicator--critical {
  background-color: rgba(var(--v-theme-error), 0.2);
  color: rgb(var(--v-theme-error));
}

.task-indicator--more {
  background-color: rgba(var(--v-theme-surface-variant), 0.8);
  color: rgba(var(--v-theme-on-surface), 0.6);
  text-align: center;
}

.task-indicator-text {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}

.gap-2 {
  gap: 8px;
}
</style>
