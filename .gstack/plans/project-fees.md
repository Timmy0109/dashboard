# Project Fees 模組

**Branch**: `feat/project-fees`
**Status**: implementing C1
**Estimated**: ~30h (含 state log + 發票上傳)

## 商業需求

- 每個 Project 有「總費用」概念（聚合）
- 兩個費用來源：
  1. **Task 費用**：member 提交、manager 審核
  2. **行政費用**：manager 直接填，無需審核（信任 manager；admin 透過稽核報表牽制）
- 發票 / 憑證上傳：選填，但 manager 可要求補件 / 代上傳

## 拍板的決策

| # | 議題 | 決定 |
|---|---|---|
| 1 | Member 駁回後可重送？ | ✅ 可 (status → pending) |
| 2 | Manager 已核定可反悔？ | ✅ 可，需填原因 |
| 3 | Member 看別人提的費用 | ❌ 只看 project 總費用，不看明細 |
| 4 | 行政費用 category | ❌ 不要 |
| 5 | 發票上傳強制？ | ❌ 選填，manager 可要求補件 |
| 6 | State log 表 | ✅ 做（完整稽核 + 反悔軌跡） |
| 7 | 多階審核 | ❌ MVP 單階；schema 預留擴充 |

## Schema

### `task_fees`
- id, task_id, project_id (denormalized), submitted_by
- amount (decimal 10,2), note
- status (string indexed: pending / approved / rejected)
- reviewed_by, reviewed_at, reject_reason
- unapproved_by, unapproved_at, unapprove_reason
- timestamps, softDeletes
- INDEX (project_id, status)

### `project_admin_fees`
- id, project_id, created_by
- amount (decimal 10,2), note, incurred_on
- timestamps, softDeletes

### `task_attachments` (alter)
- 加 task_fee_id nullable FK + index

### `project_admin_fee_attachments` (new)
- id, project_admin_fee_id, uploader_id
- original_name, disk_path, mime_type, size
- timestamps

### `task_fee_state_logs` (new)
- id, task_fee_id, from_status, to_status
- actor_id, reason (nullable)
- created_at（no updated_at — append-only）

## 狀態機（task_fees）

```
pending ──approve──→ approved
pending ──reject ──→ rejected
rejected ──resubmit (member edit)──→ pending
approved ──unapprove (manager)──→ pending
```

每次轉移：寫一筆 state log + 更新主表 status / actor / timestamp

## API

### Member 視角
- `GET /api/tasks/{task}/fees` — 本 task 的費用（only own + status visible）
- `POST /api/tasks/{task}/fees`
- `PATCH /api/task-fees/{fee}` (僅 pending / rejected)
- `DELETE /api/task-fees/{fee}` (soft delete)
- `POST /api/task-fees/{fee}/resubmit` (rejected → pending)

### Manager 視角
- `GET /api/projects/{project}/pending-fees`
- `POST /api/task-fees/{fee}/approve`
- `POST /api/task-fees/{fee}/reject` (body: reject_reason)
- `POST /api/task-fees/{fee}/unapprove` (body: unapprove_reason)
- `POST /api/task-fees/{fee}/request-receipt` (要求補件)

### Project 行政費用
- `GET /api/projects/{project}/admin-fees`
- `POST /api/projects/{project}/admin-fees`
- `PATCH /api/project-admin-fees/{fee}`
- `DELETE /api/project-admin-fees/{fee}`

### 彙總
- `GET /api/projects/{project}/fee-summary`
  - { total, task_fees_approved, task_fees_pending, admin_fees }

## 權限

| 動作 | Admin | Manager | Member (assignee) | Member (其他) |
|---|---|---|---|---|
| 提交 task fee | ✗ | ✓ | ✓ | ✗ |
| 看自己 task fee 明細 | ✓ | ✓ | ✓ | ✗ |
| 看 project 全部 task fee 明細 | ✓ | ✓ | ✗ | ✗ |
| 看 project 總費用 | ✓ | ✓ | ✓ | ✗ |
| Approve / reject / unapprove | ✓ | ✓ | ✗ | ✗ |
| 行政費用 CRUD | ✓ | ✓ | ✗ | ✗ |

## 通知 type

- `fee_submitted`
- `fee_approved`
- `fee_rejected`
- `fee_unapproved` ← 新加
- `fee_receipt_requested` ← 新加

## 提交順序

- **C1**: migrations + Models + relations
- **C2**: Controllers + Policies + Feature tests
- **C3**: Pinia store + router + notification types
- **C4**: TaskFeesPanel + TaskFeeForm + 發票上傳
- **C5**: ProjectAdminFeesTab + ProjectFeeSummary
- **C6**: PendingFeesView 暫緩；先在 TaskDetail 內審核

## 風險

- 🟡 多幣別 — MVP 單一 TWD，schema 不加 currency 欄位
- 🟡 ProjectDetail 已有多 tab，加 fees tab 要注意 layout
- 🟢 通知系統、broadcast、Pinia 模式現成
