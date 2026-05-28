# API Reconciliation — `handoff/` spec ↔ existing `routes/api.php`

> **Wave 0 deliverable** — required reading for every sub-agent. Resolves the gap
> between handoff design doc terminology and the actual routes implemented in
> this codebase. **Sub-agents must use the "Actual Path" column** when writing
> API calls; the handoff column is reference only.

Branch: `feat/ui-redesign` · Generated 2026-05-28

---

## 1 · Existing routes preserved (no change)

### Auth / Profile
| Handoff says | Actual path | Notes |
|---|---|---|
| `POST /api/login` | `POST /api/login` | unchanged |
| `POST /api/logout` | `POST /api/logout` | unchanged |
| `GET /api/me` | `GET /api/me` | unchanged |
| `PUT /api/profile` | `PUT /api/profile` | unchanged |
| `POST /api/profile/avatar` | `POST /api/profile/avatar` | unchanged |

### Dashboard / Stats
| Handoff | Actual | Notes |
|---|---|---|
| `GET /api/dashboard` | `GET /api/dashboard` | unchanged |
| `GET /api/dashboard/my-tasks` | `GET /api/dashboard/my-tasks` | unchanged |
| `GET /api/stats` | `GET /api/stats` | unchanged |
| `GET /api/stats/member/:id` | `GET /api/stats/member/{userId}` | unchanged |

### Projects + Tasks
| Handoff | Actual | Notes |
|---|---|---|
| `GET /api/projects` | `GET /api/projects` | unchanged |
| `GET /api/projects/:id` | `GET /api/projects/{project}` | unchanged |
| `POST /api/projects` | `POST /api/projects` | unchanged |
| `PUT /api/projects/:id` | `PUT /api/projects/{project}` | unchanged |
| `DELETE /api/projects/:id` | `DELETE /api/projects/{project}` | unchanged |
| `POST /api/projects/:id/tasks` | `POST /api/projects/{project}/tasks` | unchanged |
| `PUT /api/projects/:id/tasks/:tid` | `PUT /api/projects/{project}/tasks/{task}` | unchanged |
| `DELETE /api/projects/:id/tasks/:tid` | `DELETE /api/projects/{project}/tasks/{task}` | unchanged |

### Task sub-resources — **nested under project (not flat as handoff implies)**
| Handoff says | Actual path | Notes |
|---|---|---|
| `GET /api/tasks/:tid/attachments` | `GET /api/projects/{project}/tasks/{task}/attachments` | **Different**. Sub-agents: use the nested form. |
| `POST /api/tasks/:tid/attachments` | `POST /api/projects/{project}/tasks/{task}/attachments` | Different. |
| `DELETE /api/attachments/:id` | `DELETE /api/projects/{project}/tasks/{task}/attachments/{attachment}` | Different. |
| `GET /api/tasks/:tid/comments` | `GET /api/projects/{project}/tasks/{task}/comments` | Different. |
| `POST /api/tasks/:tid/comments` | `POST /api/projects/{project}/tasks/{task}/comments` | Different. |
| `DELETE /api/comments/:id` | `DELETE /api/projects/{project}/tasks/{task}/comments/{comment}` | Different. |
| `GET /api/tasks/:tid/history` | `GET /api/projects/{project}/tasks/{task}/activities` | **Different verb** (`activities` not `history`) and nested. |
| `GET /api/attachments/:id/download` | `GET /api/attachments/{attachment}/download?signature=...` | Signed URL only, no `auth:sanctum`. |

---

## 2 · NEW endpoints added in Wave 0

### Comment reply
| Path | Method | Auth | Purpose |
|---|---|---|---|
| `/api/projects/{project}/tasks/{task}/comments/{comment}/reply` | POST | `auth:sanctum` + module:comments | Create a reply (sets `parent_id`). |

Body: `{ body: string }`. Response: nested Comment (with optional `replies: []`).

**Authorization**: requesting user must be project member (admin / owner / explicit member in `project_members`).

**Validation**: `parent_id` MUST belong to the same task. Reply-to-reply NOT supported (1 level only) — if `parent.parent_id IS NOT NULL`, return 422 "cannot reply to a reply".

### Project-wide attachments
| Path | Method | Auth | Purpose |
|---|---|---|---|
| `/api/projects/{project}/attachments` | GET | `auth:sanctum` + module:attachments | List all attachments across the project (cursor-paginated). |

Query: `?cursor=<base64>&per_page=50` (default 50, max 100).
Response: `{ data: ProjectAttachment[], next_cursor: string | null }`.

**Authorization**: project member check identical to existing project-detail endpoints.
**N+1**: eager-load `with(['task:id,name', 'uploader:id,name'])`.

### Notifications
| Path | Method | Auth | Purpose |
|---|---|---|---|
| `/api/notifications` | GET | `auth:sanctum` | List current user's notifications (cursor-paginated, optional `?filter=unread`). |
| `/api/notifications/{notification}/read` | POST | `auth:sanctum` | Mark single notification as read. |
| `/api/notifications/mark-all-read` | POST | `auth:sanctum` | Mark all unread notifications as read. |

**Authorization**: each row scoped by `user_id === auth()->id()`. 404 on cross-user access.
Response shape: `PaginatedNotifications` (see `frontend/src/types/notification.ts`).

### Activity feed
| Path | Method | Auth | Purpose |
|---|---|---|---|
| `/api/activity` | GET | `auth:sanctum` | Scoped activity feed. Query: `?scope=company` (default) or `?scope=project:{id}`. |

**Authorization**:
- `scope=company`: list activities from projects the user is a member of OR owns OR (if admin/manager) all projects in user's company.
- `scope=project:{id}`: require explicit project membership check.

**N+1**: eager-load `with(['actor:id,name,avatar', 'task:id,name,project_id', 'task.project:id,name'])`.
**Pagination**: cursor, 50/page default.

---

## 3 · NEW WebSocket channels

| Channel | Type | Auth predicate (in `routes/channels.php`) |
|---|---|---|
| `task.{taskId}` | private | `$user->company_id === Task::find($taskId)?->project?->company_id` |
| `notifications.{userId}` | private | `$user->id === (int)$userId` |
| `company.{companyId}.activity` | private | `$user->company_id === (int)$companyId` |
| `project.{projectId}` | private | (existing — unchanged) |

All channels require Sanctum cookie auth.

---

## 4 · NEW broadcast events

| Event | Channel | Triggered by |
|---|---|---|
| `CommentCreated` | `task.{taskId}` | `TaskCommentController::store` |
| `CommentReplied` | `task.{taskId}` | `TaskCommentController::reply` |
| `AttachmentUploaded` | `task.{taskId}` | `TaskAttachmentController::store` |
| `HistoryEventRecorded` | `task.{taskId}` | `TaskObserver::saved` (any tracked change) |
| `NotificationCreated` | `notifications.{userId}` | `TaskObserver` / `TaskCommentObserver` (mention/assign/status/reply) |
| `NotificationRead` | `notifications.{userId}` | `NotificationController::markRead` & `markAllRead` |
| `ActivityRecorded` | `company.{companyId}.activity` | `TaskObserver` after `task_activities` insert |

**Implementation rule**: every event MUST `implements ShouldBroadcast, ShouldQueue` to avoid blocking controllers. `.env` must set `BROADCAST_CONNECTION=reverb` and `QUEUE_CONNECTION=database` (dev) or `redis` (prod).

**Broadcast failure rescue (per CEO plan Finding 2A)**:
Wrap every `broadcast(new XxxEvent(...))` call in a `SafeBroadcast::dispatch()` helper that fires fire-and-forget + logs warning on Reverb daemon failure. Do not raise from controllers.

---

## 5 · @mention data source

@mention dropdown in `TaskCommentComposer` uses **existing** `GET /api/projects/{project}/members` endpoint (already implemented at `ProjectController::members`). No new endpoint needed.

---

## 6 · Authorization matrix

| Action | Admin | Manager | Member | Notes |
|---|:-:|:-:|:-:|---|
| Read project | own company | own company | own projects only | scope via company_id |
| Edit project | ✅ | own projects | ❌ | manager has owner-level on their projects |
| Create task | ✅ | own projects | ❌ | |
| Reply to comment | ✅ | project members | project members | new |
| Read notification | self only | self only | self only | strict per-user |
| Mark notification read | self only | self only | self only | |
| Read activity feed (company scope) | own company | own company | own company filtered by projects user is member of | |
| Read activity feed (project scope) | ✅ | project members | project members | |
| Receive broadcast on `task.{id}` | ✅ | project members | project members | channel auth |
| Receive broadcast on `notifications.{user_id}` | self only | self only | self only | |
| Receive broadcast on `company.{id}.activity` | ✅ if matches | ✅ if matches | ✅ if matches | filtered by visible projects on frontend |
