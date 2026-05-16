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
    view_mode?: 'Hour' | 'Quarter Day' | 'Half Day' | 'Day' | 'Week' | 'Month' | 'Year'
    date_format?: string
    popup?: false | ((task: GanttTask) => string)
    popup_on?: 'click' | 'hover'
    today_button?: boolean
    view_mode_select?: boolean
    readonly?: boolean
    readonly_dates?: boolean
    readonly_progress?: boolean
    on_click?: (task: GanttTask) => void
    on_date_change?: (task: GanttTask, start: Date, end: Date) => void
    on_progress_change?: (task: GanttTask, progress: number) => void
    on_view_change?: (mode: string) => void
    container_height?: number | 'auto'
    bar_height?: number
    scroll_to?: string
  }

  export default class Gantt {
    constructor(element: HTMLElement | string, tasks: GanttTask[], options?: GanttOptions)
    change_view_mode(mode: string): void
    refresh(tasks: GanttTask[]): void
  }
}
