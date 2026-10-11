-- 회원 계정과 이메일 인증 토큰을 준비합니다.
-- 이 파일은 새 데이터베이스를 위한 기준 스크립트이므로 한 번만 실행하세요.

CREATE TABLE members (
    member_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '회원 ID',
    name VARCHAR(100) NOT NULL COMMENT '회원 이름',
    email VARCHAR(255) NOT NULL COMMENT '로그인 이메일 주소',
    password_hash VARCHAR(255) NOT NULL COMMENT 'password_hash()로 생성한 비밀번호 해시',
    email_verified_at DATETIME NULL COMMENT '이메일 인증 완료 시각(UTC)',
    marketing_consent TINYINT(1) NOT NULL DEFAULT 0 COMMENT '신작·이벤트·광고성 이메일 수신 동의 여부',
    marketing_consent_updated_at DATETIME NULL COMMENT '수신 동의 상태 최종 변경 시각(UTC)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '가입 시각',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '회원 정보 수정 시각',
    PRIMARY KEY (member_id),
    UNIQUE KEY uq_members_email (email),
    KEY idx_members_email_verified (email_verified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='회원 계정 및 인증 상태';

CREATE TABLE member_email_verification_tokens (
    verification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '이메일 인증 토큰 ID',
    member_id BIGINT UNSIGNED NOT NULL COMMENT '회원 ID',
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256 토큰 해시',
    expires_at DATETIME NOT NULL COMMENT '토큰 만료 시각(UTC)',
    verified_at DATETIME NULL COMMENT '토큰 사용 시각(UTC)',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '토큰 발급 시각',
    PRIMARY KEY (verification_id),
    UNIQUE KEY uq_member_email_verification_token_hash (token_hash),
    KEY idx_member_email_verification_member (member_id),
    KEY idx_member_email_verification_expiry (expires_at, verified_at),
    CONSTRAINT fk_member_email_verification_member
        FOREIGN KEY (member_id) REFERENCES members (member_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='회원 이메일 인증 토큰';
