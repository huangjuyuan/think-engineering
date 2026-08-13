<?php

namespace utils;

/**
 * 轻量参数校验器
 *
 * 声明式定义字段校验规则，统一校验并从控制器中剥离繁琐的 if 判断。
 *
 * 自动加载约定：命名空间 utils + 类名 Validator => utils/Validator.php
 *
 * 用法示例：
 *   $errors = \utils\Validator::check($data, [
 *       'username' => 'required|alpha_dash|min:3|max:64',
 *       'nickname' => 'required|max:64',
 *       'password' => 'required|min:6|max:32',
 *       'role'     => 'in:admin,user',
 *       'status'   => 'integer|between:0,1',
 *       'email'    => 'email|max:64',
 *   ]);
 *   if ($errors) {
 *       Response::json($errors, 1, '参数校验失败');
 *   }
 *
 * 规则语法：字段名 => '规则1|规则2|规则3'
 *   内置规则（每个规则可选带参数，用 : 分隔）：
 *     required       必填（值非空，含 '0' 视为有值）
 *     string         必须是字符串
 *     integer        必须是整数
 *     number         必须是数字（整型或浮点）
 *     boolean        必须是布尔值（true/false/0/1）
 *     min:n          最小长度（字符串按字符数）或最小值（数字）
 *     max:n          最大长度（字符串按字符数）或最大值（数字）
 *     between:a,b    长度或数值必须在区间内（含边界）
 *     length:n       字符串长度必须等于 n
 *     email          必须是合法邮箱格式
 *     url            必须是合法 URL
 *     phone          必须是合法手机号（中国大陆）
 *     ip             必须是合法 IP 地址（IPv4/IPv6）
 *     json           必须是合法 JSON 字符串
 *     alpha          仅字母
 *     alpha_num      仅字母或数字
 *     alpha_dash     仅字母、数字、下划线、中划线
 *     numeric        仅数字字符（字符串型数字）
 *     in:a,b,c       取值必须在列表中（列表元素用英文逗号分隔）
 *     not_in:a,b,c   取值不能是列表中的值
 *     regex:pattern  必须匹配正则（pattern 含 | 时可用 / 包裹，如 regex:/^[0-9]+$/）
 *     date           必须是合法日期（Y-m-d）
 *     datetime       必须是合法日期时间（Y-m-d H:i:s）
 *     same:field     必须与另一字段值相同
 *     confirm        必须与 字段名_confirm 的值相同
 *
 * 返回：失败时返回 ['字段名' => ['错误信息', ...], ...]，全部通过返回空数组。
 */
class Validator
{
    /**
     * 待校验数据（引用，便于 same/confirm 读取其它字段）
     * @var array
     */
    private $data;

    /**
     * 错误信息收集
     * @var array<string, string[]>
     */
    private $errors = [];

    /**
     * 当前校验字段（用于错误信息拼装）
     * @var string
     */
    private $field;

    /**
     * 校验规则名（用于错误信息拼装）
     * @var string
     */
    private $rule;

    /**
     * 静态入口：校验一组字段
     *
     * @param array $data 待校验数据
     * @param array $rules 字段 => 规则字符串 的映射
     * @param array $messages 自定义错误信息（可选），字段.规则 => 消息，如 ['username.required' => '用户名必填']
     * @return array 校验失败的错误数组；全部通过返回空数组
     */
    public static function check(array $data, array $rules, array $messages = []): array
    {
        $instance = new self($data);
        return $instance->run($rules, $messages);
    }

    /**
     * 构造器（私有，仅通过静态方法使用）
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * 执行校验
     *
     * @param array $rules
     * @param array $messages
     * @return array
     */
    private function run(array $rules, array $messages): array
    {
        foreach ($rules as $field => $ruleString) {
            $this->field = $field;
            $ruleList = $this->parseRules($ruleString);

            foreach ($ruleList as $ruleName => $params) {
                $this->rule = $ruleName;

                // 字段为空 / 缺失时，非 required 规则直接跳过（可选字段语义）
                if (!$this->shouldValidate($field, $ruleName)) {
                    continue;
                }

                $ok = $this->validateRule($ruleName, $params);
                if (!$ok) {
                    $this->addError($ruleName, $params, $messages);
                    // 一票否决：同一字段首条失败后停止后续规则，避免堆砌无效错误
                    break;
                }
            }
        }
        return $this->errors;
    }

    /**
     * 解析规则字符串为 ['ruleName' => [param1, param2, ...], ...]
     *
     * 支持用 /.../ 包裹含 | 的正则，避免与规则分隔符冲突。
     * 示例：'regex:/^a|b$/|min:2' 会被正确拆分为 regex 和 min 两条规则。
     *
     * @param string $ruleString
     * @return array
     */
    private function parseRules(string $ruleString): array
    {
        $result = [];
        $buffer = '';
        $inRegex = false;

        $len = strlen($ruleString);
        for ($i = 0; $i < $len; $i++) {
            $ch = $ruleString[$i];

            if ($ch === '/' && !$inRegex) {
                $inRegex = true;
                $buffer .= $ch;
                continue;
            }
            if ($ch === '/' && $inRegex) {
                $inRegex = false;
                $buffer .= $ch;
                continue;
            }
            if ($ch === '|' && !$inRegex) {
                // 一个规则片段结束
                $this->appendRule($result, $buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $ch;
        }
        if ($buffer !== '') {
            $this->appendRule($result, $buffer);
        }

        return $result;
    }

    /**
     * 将单个规则片段（如 "min:3" 或 "required"）拆分并写入结果
     *
     * @param array  &$result 目标容器
     * @param string $segment 规则片段
     * @return void
     */
    private function appendRule(array &$result, string $segment): void
    {
        $segment = trim($segment);
        if ($segment === '') {
            return;
        }
        // 冒号只取第一个，避免值里含冒号被误切
        $pos = strpos($segment, ':');
        if ($pos === false) {
            $name = $segment;
            $params = [];
        } else {
            $name = substr($segment, 0, $pos);
            $params = array_map('trim', explode(',', substr($segment, $pos + 1)));
        }
        $result[$name] = $params;
    }

    /**
     * 判断字段当前是否"需要继续校验"
     *
     * required 字段始终校验（由 isNotEmpty 决定成败）；
     * 其余规则仅在字段有值时才校验，空串 / null / 空数组视为未填写，直接跳过。
     *
     * @param string $field
     * @param string $rule
     * @return bool
     */
    private function shouldValidate(string $field, string $rule): bool
    {
        if ($rule === 'required') {
            return true;
        }
        $value = $this->data[$field] ?? null;
        return $this->isNotEmpty($value);
    }

    /**
     * 校验单个规则
     *
     * @param string $rule
     * @param array  $params
     * @return bool
     */
    private function validateRule(string $rule, array $params): bool
    {
        $value = $this->data[$this->field] ?? null;

        switch ($rule) {
            case 'required':
                return $this->isNotEmpty($value);

            case 'string':
                return is_string($value);

            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false;

            case 'number':
                return is_numeric($value);

            case 'boolean':
                return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);

            case 'min':
                return $this->checkMin($value, $params);

            case 'max':
                return $this->checkMax($value, $params);

            case 'between':
                return $this->checkBetween($value, $params);

            case 'length':
                $len = (int) ($params[0] ?? 0);
                return $this->length($value) === $len;

            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;

            case 'phone':
                return (bool) preg_match('/^1[3-9]\d{9}$/', (string) $value);

            case 'ip':
                return filter_var($value, FILTER_VALIDATE_IP) !== false;

            case 'json':
                if (!is_string($value)) {
                    return false;
                }
                json_decode($value);
                return json_last_error() === JSON_ERROR_NONE;

            case 'alpha':
                return (bool) preg_match('/^[A-Za-z]+$/', (string) $value);

            case 'alpha_num':
                return (bool) preg_match('/^[A-Za-z0-9]+$/', (string) $value);

            case 'alpha_dash':
                return (bool) preg_match('/^[A-Za-z0-9_-]+$/', (string) $value);

            case 'numeric':
                return ctype_digit((string) $value);

            case 'in':
                return in_array($value, $params, true);

            case 'not_in':
                return !in_array($value, $params, true);

            case 'regex':
                $pattern = $params[0] ?? '';
                if ($pattern === '') {
                    return true; // 无正则视为不校验
                }
                return (bool) preg_match($pattern, (string) $value);

            case 'date':
                return $this->isDate($value, 'Y-m-d');

            case 'datetime':
                return $this->isDate($value, 'Y-m-d H:i:s');

            case 'same':
                return $this->data[$this->field] === ($this->data[$params[0] ?? ''] ?? null);

            case 'confirm':
                return $this->data[$this->field] === ($this->data[$this->field . '_confirm'] ?? null);

            default:
                // 未知规则：宽松处理，不阻断（避免自定义规则导致连锁失败）
                return true;
        }
    }

    /**
     * 是否为数值类型（int / float，非数字字符串）
     * 用于决定 min/max/between 按数值比较还是按字符串长度比较
     */
    private function isNumericType($value): bool
    {
        return is_int($value) || is_float($value);
    }

    /**
     * min 校验：数值类型比较大小，字符串/数组比较长度
     */
    private function checkMin($value, array $params): bool
    {
        $min = (float) ($params[0] ?? 0);
        if ($this->isNumericType($value)) {
            return (float) $value >= $min;
        }
        return $this->length($value) >= (int) $min;
    }

    /**
     * max 校验：数值类型比较大小，字符串/数组比较长度
     */
    private function checkMax($value, array $params): bool
    {
        $max = (float) ($params[0] ?? 0);
        if ($this->isNumericType($value)) {
            return (float) $value <= $max;
        }
        return $this->length($value) <= (int) $max;
    }

    /**
     * between 校验：数值类型比较大小，字符串/数组比较长度（含边界）
     */
    private function checkBetween($value, array $params): bool
    {
        $min = (float) ($params[0] ?? 0);
        $max = (float) ($params[1] ?? $min);
        if ($this->isNumericType($value)) {
            $v = (float) $value;
            return $v >= $min && $v <= $max;
        }
        $len = $this->length($value);
        return $len >= (int) $min && $len <= (int) $max;
    }

    /**
     * 校验日期格式是否合法
     */
    private function isDate($value, string $format): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }
        $d = \DateTime::createFromFormat($format, $value);
        return $d !== false && $d->format($format) === $value;
    }

    /**
     * 判断值是否"非空"（空串、null、空数组视为空；'0' 视为有值）
     */
    private function isNotEmpty($value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (is_array($value)) {
            return count($value) > 0;
        }
        return true;
    }

    /**
     * 获取字符串长度（多字节安全；非字符串按标量转字符串）
     */
    private function length($value): int
    {
        if (is_array($value)) {
            return count($value);
        }
        if (function_exists('mb_strlen')) {
            return mb_strlen((string) $value);
        }
        return strlen((string) $value);
    }

    /**
     * 记录一条错误信息
     *
     * @param string $rule
     * @param array  $params
     * @param array  $messages 用户自定义错误信息
     * @return void
     */
    private function addError(string $rule, array $params, array $messages): void
    {
        $key = $this->field . '.' . $rule;
        if (isset($messages[$key])) {
            $msg = $messages[$key];
        } else {
            $msg = $this->defaultMessage($rule, $params);
        }
        $this->errors[$this->field][] = $msg;
    }

    /**
     * 生成默认错误信息（中文，字段名 + 规则描述）
     *
     * @param string $rule
     * @param array  $params
     * @return string
     */
    private function defaultMessage(string $rule, array $params): string
    {
        $field = $this->field;
        $msg = '';

        switch ($rule) {
            case 'required':    $msg = "{$field} 不能为空"; break;
            case 'string':      $msg = "{$field} 必须是字符串"; break;
            case 'integer':     $msg = "{$field} 必须是整数"; break;
            case 'number':      $msg = "{$field} 必须是数字"; break;
            case 'boolean':     $msg = "{$field} 必须是布尔值"; break;
            case 'min':         $msg = "{$field} 不能小于 {$params[0]}"; break;
            case 'max':         $msg = "{$field} 不能大于 {$params[0]}"; break;
            case 'between':     $msg = "{$field} 必须在 {$params[0]} 和 {$params[1]} 之间"; break;
            case 'length':      $msg = "{$field} 长度必须等于 {$params[0]}"; break;
            case 'email':       $msg = "{$field} 不是合法的邮箱地址"; break;
            case 'url':         $msg = "{$field} 不是合法的 URL"; break;
            case 'phone':       $msg = "{$field} 不是合法的手机号"; break;
            case 'ip':          $msg = "{$field} 不是合法的 IP 地址"; break;
            case 'json':        $msg = "{$field} 不是合法的 JSON 字符串"; break;
            case 'alpha':       $msg = "{$field} 只能包含字母"; break;
            case 'alpha_num':   $msg = "{$field} 只能包含字母或数字"; break;
            case 'alpha_dash':  $msg = "{$field} 只能包含字母、数字、下划线或中划线"; break;
            case 'numeric':     $msg = "{$field} 只能包含数字"; break;
            case 'in':          $msg = "{$field} 取值不在允许范围内"; break;
            case 'not_in':      $msg = "{$field} 取值在禁止范围内"; break;
            case 'regex':       $msg = "{$field} 格式不正确"; break;
            case 'date':        $msg = "{$field} 不是合法的日期"; break;
            case 'datetime':    $msg = "{$field} 不是合法的日期时间"; break;
            case 'same':        $msg = "{$field} 必须与 {$params[0]} 一致"; break;
            case 'confirm':     $msg = "{$field} 两次输入不一致"; break;
            default:            $msg = "{$field} 校验失败"; break;
        }
        return $msg;
    }
}
