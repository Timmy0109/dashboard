declare module 'frappe-gantt' {
  interface GanttTask {
    id: string
    name: string
    start: string
    end: string
    progress: number
    custom_class?: string
    dependencies?: string
  }

  interface GanttOptions {
    view_mode?: 'Day' | 'Week' | 'Month' | 'Year'
    date_format?: string
    popup?: boolean | ((task: GanttTask) => string)
    on_click?: (task: GanttTask) => void
    on_date_change?: (task: GanttTask, start: Date, end: Date) => void
    on_progress_change?: (task: GanttTask, progress: number) => void
  }

  export default class Gantt {
    constructor(element: HTMLElement | string, tasks: GanttTask[], options?: GanttOptions)
    change_view_mode(mode: string): void
    refresh(tasks: GanttTask[]): void
  }
}
