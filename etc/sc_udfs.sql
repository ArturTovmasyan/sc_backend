DROP FUNCTION IF EXISTS `udf_FirstNumberPos`;
DROP FUNCTION IF EXISTS `udf_NaturalSortFormat`;
DROP FUNCTION IF EXISTS `udf_PaymentSourceDecorator`;

CREATE FUNCTION `udf_FirstNumberPos` (`instring` varchar(4000))
    RETURNS int
    LANGUAGE SQL
    DETERMINISTIC
    NO SQL
    SQL SECURITY INVOKER
BEGIN
    DECLARE position int;
    DECLARE tmp_position int;
    SET position = 5000;
    SET tmp_position = LOCATE('0', instring); IF (tmp_position > 0 AND tmp_position < position) THEN SET position = tmp_position; END IF;
    SET tmp_position = LOCATE('1', instring); IF (tmp_position > 0 AND tmp_position < position) THEN SET position = tmp_position; END IF;
    SET tmp_position = LOCATE('2', instring); IF (tmp_position > 0 AND tmp_position < position) THEN SET position = tmp_position; END IF;
    SET tmp_position = LOCATE('3', instring); IF (tmp_position > 0 AND tmp_position < position) THEN SET position = tmp_position; END IF;
    SET tmp_position = LOCATE('4', instring); IF (tmp_position > 0 AND tmp_position < position) THEN SET position = tmp_position; END IF;
    SET tmp_position = LOCATE('5', instring); IF (tmp_position > 0 AND tmp_position < position) THEN SET position = tmp_position; END IF;
    SET tmp_position = LOCATE('6', instring); IF (tmp_position > 0 AND tmp_position < position) THEN SET position = tmp_position; END IF;
    SET tmp_position = LOCATE('7', instring); IF (tmp_position > 0 AND tmp_position < position) THEN SET position = tmp_position; END IF;
    SET tmp_position = LOCATE('8', instring); IF (tmp_position > 0 AND tmp_position < position) THEN SET position = tmp_position; END IF;
    SET tmp_position = LOCATE('9', instring); IF (tmp_position > 0 AND tmp_position < position) THEN SET position = tmp_position; END IF;

    IF (position = 5000) THEN RETURN 0; END IF;
    RETURN position;
END;

CREATE FUNCTION `udf_NaturalSortFormat` (`instring` varchar(4000), `numberLength` int, `sameOrderChars` char(50))
    RETURNS varchar(4000)
    LANGUAGE SQL
    DETERMINISTIC
    NO SQL
    SQL SECURITY INVOKER
BEGIN
    DECLARE sortString varchar(4000);
    DECLARE numStartIndex int;
    DECLARE numEndIndex int;
    DECLARE padLength int;
    DECLARE totalPadLength int;
    DECLARE i int;
    DECLARE sameOrderCharsLen int;

    SET totalPadLength = 0;
    SET instring = TRIM(instring);
    SET sortString = instring;
    SET numStartIndex = udf_FirstNumberPos(instring);
    SET numEndIndex = 0;
    SET i = 1;
    SET sameOrderCharsLen = CHAR_LENGTH(sameOrderChars);

    WHILE (i <= sameOrderCharsLen) DO
    SET sortString = REPLACE(sortString, SUBSTRING(sameOrderChars, i, 1), ' ');
    SET i = i + 1;
    END WHILE;

    WHILE (numStartIndex <> 0) DO
    SET numStartIndex = numStartIndex + numEndIndex;
    SET numEndIndex = numStartIndex;

    WHILE (udf_FirstNumberPos(SUBSTRING(instring, numEndIndex, 1)) = 1) DO
    SET numEndIndex = numEndIndex + 1;
    END WHILE;

    SET numEndIndex = numEndIndex - 1;

    SET padLength = numberLength - (numEndIndex + 1 - numStartIndex);

    IF padLength < 0 THEN
        SET padLength = 0;
    END IF;

    SET sortString = INSERT(sortString, numStartIndex + totalPadLength, 0, REPEAT('0', padLength));

    SET totalPadLength = totalPadLength + padLength;
    SET numStartIndex = udf_FirstNumberPos(RIGHT(instring, CHAR_LENGTH(instring) - numEndIndex));
    END WHILE;

    RETURN sortString;
END;
CREATE FUNCTION `udf_PaymentSourceDecorator`(input VARCHAR(4000)) RETURNS JSON
    DETERMINISTIC
BEGIN
    DECLARE payment_source JSON DEFAULT '[]';
    DECLARE payment_source_id INT;
    DECLARE payment_source_title VARCHAR(50);
    DECLARE payment_source_amount DOUBLE;
    DECLARE i INT DEFAULT 0;

    WHILE i < JSON_LENGTH(input) DO
    SELECT JSON_EXTRACT(input, CONCAT('$[', i, '].id')) INTO payment_source_id;
    SELECT JSON_EXTRACT(input, CONCAT('$[', i, '].amount')) INTO payment_source_amount;
    SELECT `tbl_payment_source`.`title` INTO payment_source_title
    FROM `tbl_payment_source`
    WHERE `tbl_payment_source`.`id` = payment_source_id;

    IF payment_source_title IS NOT NULL THEN
        SELECT JSON_ARRAY_APPEND(payment_source, '$',
                                 JSON_OBJECT(payment_source_title, payment_source_amount)) INTO payment_source;
    END IF;

    SELECT i + 1 INTO i;
    END WHILE;

    RETURN payment_source;
END;
