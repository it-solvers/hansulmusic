-- Add account and marketing-consent fields to the application's members table.
ALTER TABLE members
    ADD COLUMN password_hash VARCHAR(255) NULL
        COMMENT '비밀번호 해시' AFTER email,
    ADD COLUMN marketing_consent TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '신작·이벤트·광고성 이메일 수신 동의 여부',
    ADD COLUMN marketing_consent_updated_at DATETIME NULL
        COMMENT '수신 동의 상태 최종 변경 시각(UTC)';
