<?php

namespace Tests\Unit\FormRequestProbe {
    use System\Http\FormRequest;

    class SaveNoteRequest extends FormRequest
    {
        public static bool $allowed = true;

        public function authorize(): bool
        {
            return self::$allowed;
        }

        public function rules(): array
        {
            return [
                'title'       => 'required|string|max:20',
                'tags'        => 'nullable|array',
                'tags.*'      => 'string|max:10',
                'pinned'      => 'nullable|boolean',
            ];
        }

        protected function prepare(array $data): array
        {
            $data['title'] = ucfirst((string) ($data['title'] ?? ''));
            return $data;
        }
    }
}

namespace Tests\Unit {

    use System\Container\Container;
    use System\Http\HttpException;
    use System\Http\Request;
    use System\Testing\TestCase;
    use Tests\Unit\FormRequestProbe\SaveNoteRequest;

    class FormRequestTest extends TestCase
    {
        protected array $post;

        public function setUp(): void
        {
            $this->post = $_POST;
            SaveNoteRequest::$allowed = true;
        }

        public function tearDown(): void
        {
            $_POST = $this->post;
            Request::setRouteParameters([]);
        }

        public function testValidInputIsPreparedAndLimitedToTheRules(): void
        {
            $_POST = ['title' => 'groceries', 'tags' => ['home', 'food'], 'is_admin' => '1', '_token' => 'x'];
            $request = Container::getInstance()->make(SaveNoteRequest::class);

            $this->assertSame(['title' => 'Groceries', 'tags' => ['home', 'food']], $request->validated(), 'prepare() ran; unknown fields are dropped');
            $this->assertSame('Groceries', $request->validated('title'));
            $this->assertSame(['title' => 'Groceries'], $request->only(['title', 'pinned']));
            $this->assertTrue($request->has('tags'));
            $this->assertFalse($request->has('pinned'));
            $this->assertSame('1', $request->input('is_admin'), 'input() still reads anything that was sent');
        }

        public function testActionsReceiveTheValidatedRequest(): void
        {
            $_POST = ['title' => 'hello'];
            $title = Container::getInstance()->call(fn(SaveNoteRequest $request) => $request->validated('title'));
            $this->assertSame('Hello', $title);
        }

        public function testUnauthorizedIs403(): void
        {
            SaveNoteRequest::$allowed = false;
            $_POST = ['title' => 'x'];
            try {
                Container::getInstance()->make(SaveNoteRequest::class);
                throw new \System\Testing\AssertionFailed('Expected a 403');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->status());
            }
        }

        public function testRouteParameters(): void
        {
            Request::setRouteParameters(['product' => '20']);
            $_POST = ['title' => 'x'];
            $request = Container::getInstance()->make(SaveNoteRequest::class);
            $this->assertSame('20', $request->route('product'));
            $this->assertNull($request->route('missing'));
            $this->assertSame(['product' => '20'], Request::route());
        }
    }
}
