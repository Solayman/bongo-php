@extends('layouts.admin')

<?php
$editing = $post !== null;
$value = fn(string $field, $default = '') => old($field, $editing ? ($post->$field ?? $default) : $default);
$invalid = fn(string $field) => error($field) ? 'is-invalid' : '';
?>

@set('title', $editing ? __('Edit Post') : __('New Post'))

@section('content')
<div class="mb-3">
    <a href="<?= url('/posts') ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to posts</a>
    <h2 class="h4 mt-2"><?= $editing ? 'Edit ' . e($post->title) : 'New Post' ?></h2>
</div>

<form method="POST" action="<?= $editing ? url('/posts/' . $post->id) : url('/posts') ?>" novalidate>
    @csrf
    <?= $editing ? method_field('PUT') : '' ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="title">Post Title</label>
                        <input class="form-control form-control-lg <?= $invalid('title') ?>" id="title" name="title" value="{{ $value('title') }}" placeholder="Enter post title...">
                        <div class="invalid-feedback">{{ error('title') }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="editor">Content</label>
                        <textarea class="form-control <?= $invalid('content') ?>" id="editor" name="content" rows="16">{{ $value('content') }}</textarea>
                        <div class="invalid-feedback">{{ error('content') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-light fw-semibold">Publish Settings</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select <?= $invalid('status') ?>" id="status" name="status">
                            <option value="published" @selected($value('status', 'published') === 'published')>Published</option>
                            <option value="draft" @selected($value('status') === 'draft')>Draft</option>
                        </select>
                        <div class="invalid-feedback">{{ error('status') }}</div>
                    </div>

                    @if($editing)
                        <div class="mb-3 small text-secondary">
                            <div><strong>Slug:</strong> <code>{{ $post->slug }}</code></div>
                            <div><strong>Created:</strong> <?= date('Y-m-d H:i', strtotime($post->created_at)) ?></div>
                        </div>
                    @endif

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> <?= $editing ? 'Update Post' : 'Publish Post' ?>
                        </button>
                        <a href="<?= url('/posts') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-light fw-semibold d-flex justify-content-between align-items-center">
                    <span>Featured Image</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btn_clear_image" style="<?= $value('featured_image') ? '' : 'display: none;' ?>">Remove</button>
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <img id="image_preview" src="{{ $value('featured_image') ?: 'data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22300%22%20height%3D%22180%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20300%20180%22%20preserveAspectRatio%3D%22none%22%3E%3Cdefs%3E%3Cstyle%20type%3D%22text%2Fcss%22%3E%23holder_1%20text%20%7B%20fill%3Argba(150%2C150%2C150%2C.6)%3Bfont-weight%3Anormal%3Bfont-family%3AHelvetica%2C%20monospace%3Bfont-size%3A14pt%20%7D%20%3C%2Fstyle%3E%3C%2Fdefs%3E%3Cg%20id%3D%22holder_1%22%3E%3Crect%20width%3D%22300%22%20height%3D%22180%22%20fill%3D%22%23f3f4f6%22%3E%3C%2Frect%3E%3Cg%3E%3Ctext%20x%3D%22100%22%20y%3D%2295%22%3ENo%20Image%3C%2Ftext%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E' }}" class="img-fluid rounded border mb-2" style="max-height: 180px; width: 100%; object-fit: cover;" alt="Featured Image Preview">
                    </div>

                    <input type="hidden" id="featured_image" name="featured_image" value="{{ $value('featured_image') }}">

                    <button type="button" class="btn btn-outline-dark w-100" id="btn_select_image">
                        <i class="bi bi-folder2-open me-1"></i> Choose from File Manager
                    </button>
                    <div class="form-text mt-2">Pick or upload images directly through Bongo File Manager.</div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('scripts')
<!-- TinyMCE 6 (Zero-config CDN) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js"></script>
<!-- Bongo File Manager Helper Script -->
<script src="<?= url('/filemanager/script') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Standalone Featured Image Picker with Bongo File Manager
    bongoFileManager({
        button: '#btn_select_image',
        targetInput: '#featured_image',
        targetPreview: '#image_preview',
        type: 'image',
        prefix: '<?= url('/filemanager') ?>',
        onSelect: function (item) {
            document.getElementById('btn_clear_image').style.display = 'inline-block';
        }
    });

    // Clear Featured Image Button
    const clearBtn = document.getElementById('btn_clear_image');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            document.getElementById('featured_image').value = '';
            document.getElementById('image_preview').src = 'data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22300%22%20height%3D%22180%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20300%20180%22%20preserveAspectRatio%3D%22none%22%3E%3Cdefs%3E%3Cstyle%20type%3D%22text%2Fcss%22%3E%23holder_1%20text%20%7B%20fill%3Argba(150%2C150%2C150%2C.6)%3Bfont-weight%3Anormal%3Bfont-family%3AHelvetica%2C%20monospace%3Bfont-size%3A14pt%20%7D%20%3C%2Fstyle%3E%3C%2Fdefs%3E%3Cg%20id%3D%22holder_1%22%3E%3Crect%20width%3D%22300%22%20height%3D%22180%22%20fill%3D%22%23f3f4f6%22%3E%3C%2Frect%3E%3Cg%3E%3Ctext%20x%3D%22100%22%20y%3D%2295%22%3ENo%20Image%3C%2Ftext%3E%3C%2Fg%3E%3C%2Fg%3E%3C%2Fsvg%3E';
            clearBtn.style.display = 'none';
        });
    }

    // 2. TinyMCE 6 integration with Bongo File Manager
    tinymce.init({
        selector: 'textarea#editor',
        height: 520,
        menubar: 'file edit view insert format tools table',
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount'
        ],
        toolbar: 'undo redo | blocks | ' +
            'bold italic forecolor backcolor | alignleft aligncenter ' +
            'alignright alignjustify | bullist numlist outdent indent | ' +
            'link image media | removeformat | code fullscreen help',
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.6; } img { max-width: 100%; height: auto; }',
        file_picker_callback: bongoTinyMCEPicker({
            prefix: '<?= url('/filemanager') ?>'
        })
    });
});
</script>
@endsection
