<?php

declare(strict_types=1);

return [
    'spacing' => 'Spacing', 'spacing_compact' => 'Compact', 'spacing_comfortable' => 'Comfortable', 'spacing_spacious' => 'Spacious', 'theme_default' => 'theme default',
    // Top bar
    'all_pages' => 'All pages', 'back' => '← Pages', 'page_title' => 'Page title', 'slug' => 'URL slug',
    'draft' => 'Draft', 'published' => 'Published', 'saved' => 'Saved', 'unsaved' => 'Unsaved changes',
    'saving' => 'Saving…', 'not_saved' => 'Not saved', 'undo' => 'Undo (Ctrl+Z)', 'redo' => 'Redo (Ctrl+Shift+Z)',
    'preview' => 'Preview ▶', 'view' => 'View ↗', 'save' => 'Save', 'language' => 'Content language',
    'default_lang' => 'default', 'ui_theme' => 'Editor theme', 'canvas_theme' => 'Preview the page in light / dark',
    'device_desktop' => 'Desktop', 'device_tablet' => 'Tablet', 'device_mobile' => 'Mobile',

    // Panels
    'blocks' => 'Blocks', 'layers' => 'Layers', 'page' => 'Page', 'search_blocks' => 'Search blocks…',
    'drag_hint' => 'Drag onto the canvas, or click to add', 'nothing_here' => 'Nothing here yet — add a block from the Blocks tab.',
    'select_hint' => 'Select a block on the canvas to edit it.',
    'shortcuts' => 'Shortcuts: Ctrl+S save · Ctrl+Z undo · Ctrl+D duplicate · Ctrl+C/X/V copy · Del delete · Esc cancel',
    'duplicate' => 'Duplicate', 'delete' => 'Delete', 'move_up' => 'Move up', 'move_down' => 'Move down',
    'select_parent' => 'Select parent', 'drag_to_move' => 'Drag to move', 'unknown_type' => 'Unknown block', 'unknown_block' => 'This block type is not registered. Its data is preserved.',
    'content' => 'Content', 'advanced' => 'Advanced', 'group_animation' => 'Animation', 'group_layout' => 'Spacing & visibility', 'group_code' => 'Code & identity',
    'expand' => 'expand', 'upload' => 'Upload', 'done' => 'Done', 'cancel' => 'Cancel', 'remove' => 'Remove', 'close' => 'Close',
    'add_item' => '+ Add item', 'item' => 'Item', 'translating' => 'Editing the :locale version. Empty fields fall back to the default language.',
    'default_value' => 'Default', 'drop_here' => 'Drop blocks here', 'choose_image' => 'Choose an image in the inspector →',
    'add_video' => 'Paste a YouTube, Vimeo or video URL in the inspector →', 'add_lottie' => 'Paste a Lottie JSON URL in the inspector →',
    'add_locales' => 'Add more locales in config/atlas.php to show the language switcher.', 'toggle_theme' => 'Toggle light / dark mode',

    // Page settings
    'page_settings' => 'Page settings', 'theme' => 'Theme', 'theme_mode' => 'Colour mode', 'mode_auto' => 'Automatic (follow visitor)',
    'mode_light' => 'Always light', 'mode_dark' => 'Always dark', 'show_toggle' => 'Show light/dark switch to visitors',
    'accent' => 'Accent colour (light)', 'accent_dark' => 'Accent colour (dark)', 'font' => 'Body font', 'heading_font' => 'Heading font',
    'font_same' => 'Same as body', 'font_system' => 'System sans', 'font_serif' => 'Serif', 'font_mono' => 'Monospace', 'font_rounded' => 'Rounded',
    'meta_description' => 'Meta description', 'og_image' => 'Social share image', 'page_code' => 'Page code', 'page_css' => 'Page CSS',
    'page_js' => 'Page JavaScript (runs on the live page and Preview)', 'page_head' => 'Extra <head> HTML',
    'scripts_note' => 'Scripts do not run inside the editor canvas. Use Preview ▶ to test JavaScript and animations.',

    // Custom block builder
    'custom_blocks' => 'Custom blocks', 'new_block' => '＋ New custom block', 'edit_block' => 'Edit', 'block_builder' => 'Custom block builder',
    'block_label' => 'Name', 'block_type' => 'Machine name', 'block_category' => 'Group', 'block_icon' => 'Icon (emoji)', 'block_container' => 'Container (other blocks can be dropped inside)',
    'fields' => 'Fields', 'add_field' => '+ Add field', 'field_name' => 'name', 'field_label' => 'Label', 'field_type' => 'Type', 'field_default' => 'Default',
    'field_options' => 'Options  value:Label, value:Label', 'field_sub' => 'Sub-fields  name:type, name:type', 'field_translatable' => 'Translatable',
    'tpl_html' => 'HTML template', 'tpl_css' => 'CSS', 'tpl_js' => 'JavaScript', 'tpl_help' => 'Use {{ field }} (escaped), {{{ field }}} (raw), {{ url:field }} (safe link), {{#each items}}…{{/each}}, {{#if field}}…{{else}}…{{/if}} and {{ children }} in containers. CSS: {{selector}} targets the block. JS: `el` is the block.',
    'block_saved' => 'Block saved', 'block_deleted' => 'Block deleted', 'delete_block_confirm' => 'Delete this custom block? Pages using it will show it as unknown.', 'block_exists' => 'A block with that name already exists.',

    // Messages
    'unsaved_confirm' => 'You have unsaved changes. Leave anyway?', 'canvas_failed' => 'Canvas render failed', 'save_failed' => 'Save failed', 'upload_failed' => 'Upload failed',
    'pages' => 'Pages', 'new_page' => 'New page title…', 'create_page' => 'Create page', 'title' => 'Title', 'url' => 'URL', 'status' => 'Status', 'updated' => 'Updated',
    'edit' => 'Edit', 'delete_confirm' => 'Delete this page?', 'no_pages' => 'No pages yet. Create your first one above.',
];
