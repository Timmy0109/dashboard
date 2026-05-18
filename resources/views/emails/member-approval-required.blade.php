<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>新成員申請待審核</title>
</head>
<body style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 24px;">
    <h2 style="color: #00897b;">【{{ $companyName }}】新成員申請</h2>

    <p>您好，</p>
    <p>
        <strong>{{ $applicant->name }}</strong>（{{ $applicant->email }}）
        剛剛使用邀請碼申請加入 <strong>{{ $companyName }}</strong>，
        需要您前往系統審核。
    </p>

    <p style="margin-top: 24px;">
        <a href="{{ config('app.url') }}/member-approvals"
           style="background:#00897b;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;">
            前往審核
        </a>
    </p>

    <p style="margin-top: 32px; color: #888; font-size: 13px;">
        此郵件由系統自動發送，請勿直接回覆。
    </p>
</body>
</html>
