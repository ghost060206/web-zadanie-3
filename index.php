<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета - Задание 3</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #e9f0f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        h1 { text-align: center; color: #2c3e50; margin-bottom: 30px; }
        label {
            font-weight: bold;
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
        }
        .radio-group, .checkbox-group {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 5px;
        }
        .radio-group label, .checkbox-group label {
            font-weight: normal;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin: 0;
        }
        input[type="radio"], input[type="checkbox"] { width: auto; }
        select[multiple] { height: 120px; }
        .error { color: #e74c3c; font-size: 14px; margin-top: 5px; }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #28a745;
        }
        button {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 18px;
            border-radius: 30px;
            cursor: pointer;
            margin-top: 25px;
            width: 100%;
        }
        button:hover { background: #2980b9; }
    </style>
</head>
<body>
<div class="container">
    <h1>Регистрационная анкета</h1>

    <?php
    $errors = [];
    $success = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $host = 'localhost';
        $dbname = 'u82293';
        $user = 'u82293';
        $pass = '7537172';

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Валидация
            $full_name = trim($_POST['full_name'] ?? '');
            if (empty($full_name)) $errors['full_name'] = 'ФИО обязательно.';
            elseif (mb_strlen($full_name) > 150) $errors['full_name'] = 'Не более 150 символов.';
            elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $full_name)) $errors['full_name'] = 'Только буквы, пробелы, дефис.';

            $phone = trim($_POST['phone'] ?? '');
            if (empty($phone)) $errors['phone'] = 'Телефон обязателен.';
            elseif (!preg_match('/^[\+0-9\(\)\s\-]{5,20}$/', $phone)) $errors['phone'] = 'Некорректный номер.';

            $email = trim($_POST['email'] ?? '');
            if (empty($email)) $errors['email'] = 'Email обязателен.';
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Некорректный email.';

            $birth_date = $_POST['birth_date'] ?? '';
            if (empty($birth_date)) $errors['birth_date'] = 'Дата рождения обязательна.';
            else {
                $d = DateTime::createFromFormat('Y-m-d', $birth_date);
                if (!$d || $d->format('Y-m-d') !== $birth_date) $errors['birth_date'] = 'Неверный формат (ГГГГ-ММ-ДД).';
                elseif ($d->diff(new DateTime())->y > 120) $errors['birth_date'] = 'Возраст не может быть больше 120 лет.';
            }

            $gender = $_POST['gender'] ?? '';
            if (!in_array($gender, ['male', 'female', 'other'])) $errors['gender'] = 'Выберите пол.';

            $selected_languages = $_POST['languages'] ?? [];
            if (empty($selected_languages)) $errors['languages'] = 'Выберите хотя бы один язык.';
            else {
                $stmt = $pdo->query("SELECT name FROM programming_languages");
                $allowed = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($selected_languages as $lang) {
                    if (!in_array($lang, $allowed)) { $errors['languages'] = 'Недопустимый язык.'; break; }
                }
            }

            $biography = trim($_POST['biography'] ?? '');
            if (mb_strlen($biography) > 5000) $errors['biography'] = 'Биография не более 5000 символов.';

            $contract = isset($_POST['contract']) && $_POST['contract'] == '1';
            if (!$contract) $errors['contract'] = 'Необходимо согласие с контрактом.';

            if (empty($errors)) {
                $pdo->beginTransaction();
                $sql = "INSERT INTO users (full_name, phone, email, birth_date, gender, biography, contract_accepted)
                        VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':full_name' => $full_name,
                    ':phone' => $phone,
                    ':email' => $email,
                    ':birth_date' => $birth_date,
                    ':gender' => $gender,
                    ':biography' => $biography,
                    ':contract' => $contract ? 1 : 0
                ]);
                $user_id = $pdo->lastInsertId();

                $lang_stmt = $pdo->prepare("INSERT INTO user_languages (user_id, language_id)
                                            SELECT :uid, id FROM programming_languages WHERE name = :lang");
                foreach ($selected_languages as $lang) {
                    $lang_stmt->execute([':uid' => $user_id, ':lang' => $lang]);
                }
                $pdo->commit();
                $success = true;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['db'] = 'Ошибка БД: ' . $e->getMessage();
        }
    }
    ?>

    <?php if ($success): ?>
        <div class="success">✅ Данные успешно сохранены!</div>
    <?php endif; ?>

    <form method="POST" action="">
        <label>ФИО *</label>
        <input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        <?php if (isset($errors['full_name'])): ?><div class="error"><?= $errors['full_name'] ?></div><?php endif; ?>

        <label>Телефон *</label>
        <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        <?php if (isset($errors['phone'])): ?><div class="error"><?= $errors['phone'] ?></div><?php endif; ?>

        <label>Email *</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <?php if (isset($errors['email'])): ?><div class="error"><?= $errors['email'] ?></div><?php endif; ?>

        <label>Дата рождения *</label>
        <input type="date" name="birth_date" value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>">
        <?php if (isset($errors['birth_date'])): ?><div class="error"><?= $errors['birth_date'] ?></div><?php endif; ?>

        <label>Пол *</label>
        <div class="radio-group">
            <label><input type="radio" name="gender" value="male" <?= (($_POST['gender'] ?? '') == 'male') ? 'checked' : '' ?>> Мужской</label>
            <label><input type="radio" name="gender" value="female" <?= (($_POST['gender'] ?? '') == 'female') ? 'checked' : '' ?>> Женский</label>
            <label><input type="radio" name="gender" value="other" <?= (($_POST['gender'] ?? '') == 'other') ? 'checked' : '' ?>> Другой</label>
        </div>
        <?php if (isset($errors['gender'])): ?><div class="error"><?= $errors['gender'] ?></div><?php endif; ?>

        <label>Любимые языки программирования *</label>
        <select name="languages[]" multiple>
            <?php
            try {
                $pdo_temp = new PDO("mysql:host=localhost;dbname=u82293;charset=utf8", 'u82293', '7537172');
                $langs = $pdo_temp->query("SELECT name FROM programming_languages ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
                $selected = $_POST['languages'] ?? [];
                foreach ($langs as $lang) {
                    $sel = in_array($lang, $selected) ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($lang) . "\" $sel>" . htmlspecialchars($lang) . "</option>";
                }
            } catch (Exception $e) { echo "<option disabled>Ошибка загрузки языков</option>"; }
            ?>
        </select>
        <?php if (isset($errors['languages'])): ?><div class="error"><?= $errors['languages'] ?></div><?php endif; ?>

        <label>Биография</label>
        <textarea name="biography" rows="5"><?= htmlspecialchars($_POST['biography'] ?? '') ?></textarea>
        <?php if (isset($errors['biography'])): ?><div class="error"><?= $errors['biography'] ?></div><?php endif; ?>

        <div class="checkbox-group">
            <label><input type="checkbox" name="contract" value="1" <?= (isset($_POST['contract']) && $_POST['contract'] == '1') ? 'checked' : '' ?>> Я соглашаюсь с условиями контракта *</label>
        </div>
        <?php if (isset($errors['contract'])): ?><div class="error"><?= $errors['contract'] ?></div><?php endif; ?>

        <button type="submit">Сохранить</button>
    </form>
</div>
</body>
</html>
