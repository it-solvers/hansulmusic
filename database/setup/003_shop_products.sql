-- 상품 테이블을 생성하고 초기 상품을 등록한다.
-- 이미지·음원 파일 자체는 서버에 보관한다.
CREATE TABLE products (
    product_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '상품 ID',
    slug VARCHAR(100) NOT NULL COMMENT '상품 식별자',
    product_type TINYINT UNSIGNED NOT NULL COMMENT '상품 구분: 1=Scores, 2=Records',
    name VARCHAR(255) NOT NULL COMMENT '상품명',
    subtitle VARCHAR(255) NOT NULL DEFAULT '' COMMENT '상품 부제',
    description TEXT NOT NULL COMMENT '상품 설명',
    regular_price_krw INT UNSIGNED NOT NULL COMMENT '정가(원)',
    sale_price_krw INT UNSIGNED NOT NULL COMMENT '할인가(원)',
    cover_path VARCHAR(512) NULL COMMENT '표지 이미지 경로',
    preview_audio_path VARCHAR(512) NULL COMMENT '미리듣기 음원 경로',
    download_path VARCHAR(512) NOT NULL COMMENT '다운로드 파일 경로',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '진열 여부',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '진열 순서',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '등록 시각',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '수정 시각',
    PRIMARY KEY (product_id),
    UNIQUE KEY uq_products_slug (slug),
    KEY idx_products_display (is_active, product_type, sort_order, product_id),
    CONSTRAINT chk_products_type CHECK (product_type IN (1, 2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='악보와 음원 상품 정보';

CREATE TABLE product_assets (
    asset_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '파일 정보 ID',
    product_id BIGINT UNSIGNED NOT NULL COMMENT '상품 ID',
    asset_type ENUM('preview_image') NOT NULL COMMENT '파일 종류',
    asset_path VARCHAR(512) NOT NULL COMMENT '미리보기 파일 경로',
    label VARCHAR(255) NOT NULL DEFAULT '' COMMENT '미리보기 설명',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '표시 순서',
    PRIMARY KEY (asset_id),
    KEY idx_product_assets_display (product_id, asset_type, sort_order, asset_id),
    CONSTRAINT fk_product_assets_product
        FOREIGN KEY (product_id) REFERENCES products (product_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='상품 미리보기 파일 경로';

INSERT INTO products (
    slug, product_type, name, subtitle, description,
    regular_price_krw, sale_price_krw, cover_path,
    preview_audio_path, download_path, is_active, sort_order
) VALUES
    (
        'score-glory', 1, '8.1.3. 칸타타 영광영광',
        'Orchestra Score and Parts', '관현악 악보 일부를 미리 확인할 수 있습니다.',
        300000, 100000, NULL, NULL,
        'scoreszip/score-glory-preview.zip', 1, 1
    ),
    (
        'audio-featured', 2, '바람이 불었으면 좋겠어',
        '길구봉구 · MP3', '음원을 미리 들어보세요. 미리듣기는 처음 1분 제공됩니다.',
        1000, 800, NULL,
        'music/길구봉구-01-바람이 불었으면 좋겠어.mp3',
        'music/길구봉구-01-바람이 불었으면 좋겠어.mp3', 1, 2
    );

INSERT INTO product_assets (product_id, asset_type, asset_path, label, sort_order)
SELECT product_id, 'preview_image', 'scores/8.1.3. 칸타타 영광영광-08.png', '8쪽', 1
FROM products WHERE slug = 'score-glory';

INSERT INTO product_assets (product_id, asset_type, asset_path, label, sort_order)
SELECT product_id, 'preview_image', 'scores/8.1.3. 칸타타 영광영광-09.png', '9쪽', 2
FROM products WHERE slug = 'score-glory';

INSERT INTO product_assets (product_id, asset_type, asset_path, label, sort_order)
SELECT product_id, 'preview_image', 'scores/8.1.3. 칸타타 영광영광-10.png', '10쪽', 3
FROM products WHERE slug = 'score-glory';
