-- Buyurtmalar jadvaliga yangi ustunlar qo'shish
ALTER TABLE orders 
ADD COLUMN phone VARCHAR(20) AFTER comment,
ADD COLUMN address TEXT AFTER phone,
ADD COLUMN delivery_notes TEXT AFTER address;

-- Mavjud ma'lumotlarni yangilash (ixtiyoriy)
UPDATE orders o 
JOIN users u ON u.id = o.user_id 
SET o.phone = u.phone_number 
WHERE o.phone IS NULL;