-- [1단계] 기존 DB 전용 변경: products.slug를 없애고 product_type(1=악보, 2=음원)으로 통일합니다.
-- 종류마다 상품 한 개만 등록하도록 UNIQUE 키를 추가합니다. ALTER 권한이 있는 DB 관리자 계정으로 한 번만 실행하세요.
-- 새 상품 등록(admin-products.php)은 이 변경을 적용한 뒤에 동작합니다.
ALTER TABLE products
    DROP INDEX uq_products_slug,
    DROP COLUMN slug,
    ADD UNIQUE KEY uq_products_type (product_type),
    MODIFY product_type TINYINT UNSIGNED NOT NULL COMMENT '상품 종류: 1=악보(Scores), 2=음원(Records). 종류마다 한 개만 등록';
