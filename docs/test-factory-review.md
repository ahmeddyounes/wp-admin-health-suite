# Test Factory Review - Q15-04

## Overview

This document reviews the `tests/factories/` directory for proper test data generation, realistic data, and helper methods. The factories extend WordPress's built-in test factories to provide specialized data creation methods for the WP Admin Health Suite plugin.

## Factory Files

```
tests/factories/
├── PostFactory.php
├── AttachmentFactory.php
└── CommentFactory.php
```

**Total: 3 factory files**

## Factory Analysis

### PostFactory (`tests/factories/PostFactory.php`)

Extends `WP_UnitTest_Factory_For_Post` to provide custom post creation methods.

#### Methods

| Method                    | Purpose                             | Parameters                          | Return            |
| ------------------------- | ----------------------------------- | ----------------------------------- | ----------------- |
| `create_with_revisions()` | Create a post with revision history | `$revision_count = 3`, `$args = []` | Post ID           |
| `create_trashed()`        | Create a post in trash status       | `$args = []`                        | Post ID           |
| `create_many_posts()`     | Bulk create multiple posts          | `$count`, `$args = []`              | Array of Post IDs |

#### Implementation Details

**`create_with_revisions()`**

```php
public function create_with_revisions( $revision_count = 3, $args = array() ) {
    $post_id = $this->create( $args );
    add_filter( 'wp_revisions_to_keep', '__return_true' );
    for ( $i = 0; $i < $revision_count; $i++ ) {
        wp_update_post( array(
            'ID'           => $post_id,
            'post_content' => 'Revision ' . ( $i + 1 ) . ' content',
        ) );
    }
    remove_filter( 'wp_revisions_to_keep', '__return_true' );
    return $post_id;
}
```

**Evaluation:**

- Properly enables revisions via filter before creating them
- Correctly cleans up the filter after creation
- Generates sequential revision content for traceability
- Default of 3 revisions is reasonable for testing

**`create_trashed()`**

```php
public function create_trashed( $args = array() ) {
    $args['post_status'] = 'trash';
    return $this->create( $args );
}
```

**Evaluation:**

- Simple, effective implementation
- Allows additional args to be passed through

**`create_many_posts()`**

```php
public function create_many_posts( $count, $args = array() ) {
    $post_ids = array();
    for ( $i = 0; $i < $count; $i++ ) {
        $post_args = array_merge( $args, array(
            'post_title' => isset( $args['post_title'] )
                ? $args['post_title'] . ' ' . ( $i + 1 )
                : 'Post ' . ( $i + 1 ),
        ) );
        $post_ids[] = $this->create( $post_args );
    }
    return $post_ids;
}
```

**Evaluation:**

- Generates unique titles for each post (prevents ambiguity in tests)
- Allows overriding the title prefix
- Returns array of IDs for easy verification

---

### AttachmentFactory (`tests/factories/AttachmentFactory.php`)

Extends `WP_UnitTest_Factory_For_Attachment` to provide specialized attachment creation methods.

#### Methods

| Method                   | Purpose                                         | Parameters                                    | Return                  |
| ------------------------ | ----------------------------------------------- | --------------------------------------------- | ----------------------- |
| `create_image()`         | Create an image attachment with dimensions      | `$width = 800`, `$height = 600`, `$args = []` | Attachment ID           |
| `create_with_alt_text()` | Create image with alt text                      | `$alt_text`, `$args = []`                     | Attachment ID           |
| `create_orphaned()`      | Create attachment without parent post           | `$args = []`                                  | Attachment ID           |
| `create_many_for_post()` | Create multiple attachments for a post          | `$parent_id`, `$count`, `$args = []`          | Array of Attachment IDs |
| `create_large_file()`    | Create attachment with large simulated filesize | `$filesize = 5242880`, `$args = []`           | Attachment ID           |

#### Implementation Details

**`create_image()`**

```php
public function create_image( $width = 800, $height = 600, $args = array() ) {
    $defaults = array(
        'post_mime_type' => 'image/jpeg',
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
    );
    $args = wp_parse_args( $args, $defaults );
    $attachment_id = $this->create( $args );

    $metadata = array(
        'width'  => $width,
        'height' => $height,
        'file'   => 'test-image-' . $attachment_id . '.jpg',
        'sizes'  => array(),
    );
    update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
    return $attachment_id;
}
```

**Evaluation:**

- Properly sets MIME type and attachment defaults
- Creates realistic metadata structure
- Unique filename per attachment ID prevents conflicts
- Default dimensions (800x600) are reasonable for testing

**`create_with_alt_text()`**

```php
public function create_with_alt_text( $alt_text, $args = array() ) {
    $attachment_id = $this->create_image( 800, 600, $args );
    update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
    return $attachment_id;
}
```

**Evaluation:**

- Reuses `create_image()` for consistency
- Uses correct WordPress meta key for alt text

**`create_orphaned()`**

```php
public function create_orphaned( $args = array() ) {
    $args['post_parent'] = 0;
    return $this->create_image( 800, 600, $args );
}
```

**Evaluation:**

- Explicitly sets `post_parent` to 0
- Important for testing orphaned media detection

**`create_many_for_post()`**

```php
public function create_many_for_post( $parent_id, $count, $args = array() ) {
    $attachment_ids = array();
    $args['post_parent'] = $parent_id;
    for ( $i = 0; $i < $count; $i++ ) {
        $attachment_ids[] = $this->create_image( 800, 600, $args );
    }
    return $attachment_ids;
}
```

**Evaluation:**

- Properly associates attachments with parent post
- Returns array of IDs for verification

**`create_large_file()`**

```php
public function create_large_file( $filesize = 5242880, $args = array() ) {
    $attachment_id = $this->create_image( 3000, 2000, $args );
    $metadata = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
    $metadata['filesize'] = $filesize;
    update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
    return $attachment_id;
}
```

**Evaluation:**

- Uses larger dimensions (3000x2000) for oversized image testing
- Stores simulated filesize in metadata
- Default 5MB is appropriate for "large file" testing
- Does not create actual large files (efficient for tests)

---

### CommentFactory (`tests/factories/CommentFactory.php`)

Extends `WP_UnitTest_Factory_For_Comment` to provide specialized comment creation methods.

#### Methods

| Method                   | Purpose                              | Parameters                                   | Return                                 |
| ------------------------ | ------------------------------------ | -------------------------------------------- | -------------------------------------- |
| `create_spam()`          | Create a spam comment                | `$post_id`, `$args = []`                     | Comment ID                             |
| `create_trashed()`       | Create a trashed comment             | `$post_id`, `$args = []`                     | Comment ID                             |
| `create_many_for_post()` | Create multiple comments for a post  | `$post_id`, `$count`, `$args = []`           | Array of Comment IDs                   |
| `create_with_author()`   | Create comment with specific author  | `$post_id`, `$user_id`, `$args = []`         | Comment ID                             |
| `create_thread()`        | Create a parent comment with replies | `$post_id`, `$reply_count = 3`, `$args = []` | Array with 'parent' and 'replies' keys |

#### Implementation Details

**`create_spam()`**

```php
public function create_spam( $post_id, $args = array() ) {
    $args['comment_post_ID'] = $post_id;
    $args['comment_approved'] = 'spam';
    return $this->create( $args );
}
```

**Evaluation:**

- Uses correct WordPress spam status value
- Post ID is a required parameter (good API design)

**`create_trashed()`**

```php
public function create_trashed( $post_id, $args = array() ) {
    $args['comment_post_ID'] = $post_id;
    $args['comment_approved'] = 'trash';
    return $this->create( $args );
}
```

**Evaluation:**

- Uses correct WordPress trash status value
- Parallel API to `create_spam()` for consistency

**`create_many_for_post()`**

```php
public function create_many_for_post( $post_id, $count, $args = array() ) {
    $comment_ids = array();
    $args['comment_post_ID'] = $post_id;
    for ( $i = 0; $i < $count; $i++ ) {
        $comment_args = array_merge( $args, array(
            'comment_content' => isset( $args['comment_content'] )
                ? $args['comment_content'] . ' ' . ( $i + 1 )
                : 'Comment ' . ( $i + 1 ),
        ) );
        $comment_ids[] = $this->create( $comment_args );
    }
    return $comment_ids;
}
```

**Evaluation:**

- Generates unique comment content for each comment
- Allows content prefix override
- Returns array of IDs for verification

**`create_with_author()`**

```php
public function create_with_author( $post_id, $user_id, $args = array() ) {
    $args['comment_post_ID'] = $post_id;
    $args['user_id'] = $user_id;
    return $this->create( $args );
}
```

**Evaluation:**

- Associates comment with specific user
- Useful for permission/capability testing

**`create_thread()`**

```php
public function create_thread( $post_id, $reply_count = 3, $args = array() ) {
    $args['comment_post_ID'] = $post_id;
    $parent_id = $this->create( $args );

    $replies = array();
    for ( $i = 0; $i < $reply_count; $i++ ) {
        $reply_args = array_merge( $args, array(
            'comment_parent' => $parent_id,
            'comment_content' => 'Reply ' . ( $i + 1 ),
        ) );
        $replies[] = $this->create( $reply_args );
    }

    return array(
        'parent'  => $parent_id,
        'replies' => $replies,
    );
}
```

**Evaluation:**

- Creates realistic threaded comment structure
- Returns structured data with parent and replies separated
- Useful for testing nested comment operations

---

## Integration with Test Infrastructure

### TestCase Integration

The `TestCase` base class provides helper methods that use the standard WordPress factory:

```php
protected function create_test_post( $args = array() ) {
    return $this->factory()->post->create( $args );
}

protected function create_test_attachment( $args = array(), $parent_post_id = 0 ) {
    return $this->factory()->attachment->create( $args );
}

protected function create_test_comment( $args = array(), $post_id = 0 ) {
    return $this->factory()->comment->create( $args );
}
```

### Documentation in README.md

The `tests/README.md` documents factory usage:

```php
// Create a post with revisions
$this->factory()->post->create_with_revisions(5);

// Create multiple posts
$post_ids = $this->factory()->post->create_many_posts(10);

// Create orphaned attachments
$this->factory()->attachment->create_orphaned();

// Create a comment thread
$thread = $this->factory()->comment->create_thread($post_id, 3);
```

### Current Usage in Tests

**Observation:** The custom factory methods are documented but actual test files primarily use:

1. TestCase helper methods (`create_test_post()`, `create_test_attachment()`, `create_test_comment()`)
2. Direct `$this->factory()->*->create()` calls with custom args
3. Test-specific helper methods (e.g., `create_test_image()` in MediaScannerTest)

The custom factories are available but underutilized in the current test suite.

---

## Findings Summary

### Strengths

1. **Proper Extension**: All factories properly extend WordPress's built-in test factories
2. **Realistic Data**: Factories create data with proper WordPress structures (metadata, statuses, relationships)
3. **Clear API**: Methods have descriptive names and logical parameter ordering
4. **Flexibility**: All methods allow additional args to be merged
5. **Return Values**: Methods consistently return IDs or structured arrays
6. **Documentation**: PHPDoc comments explain each method's purpose

### Areas Working Well

1. **PostFactory**
    - Revision creation properly manages the `wp_revisions_to_keep` filter
    - Bulk creation generates unique titles

2. **AttachmentFactory**
    - Image metadata is properly structured
    - Large file simulation doesn't create actual large files (efficient)
    - Orphaned attachment creation explicitly sets `post_parent`

3. **CommentFactory**
    - Thread creation returns structured data (parent + replies)
    - Supports all comment statuses (approved, spam, trash)
    - User association method supports permission testing

### Observations

1. **Factory Registration**: The custom factories are defined but their registration with WordPress's factory system isn't explicitly shown in bootstrap. Tests may need to instantiate them directly or the README examples may need adjustment.

2. **Underutilization**: Current tests primarily use inline helper methods rather than the custom factories. This is functional but doesn't leverage the factory methods fully.

3. **No File Creation**: AttachmentFactory creates attachment posts and metadata but doesn't create actual physical files. This is by design (tests that need files create them explicitly in test-specific helpers like `create_test_image()` in MediaScannerTest).

4. **Test Data Realism**:
    - Post content is generic ("Revision X content", "Post X")
    - Image filenames are functional ("test-image-{id}.jpg")
    - Comment content is generic ("Comment X", "Reply X")

    This is appropriate for most tests but could be enhanced with Faker-style realistic data if needed.

---

## Recommendations

### For Current State

The factories are **well-implemented and functional**. They provide useful helper methods for common test scenarios.

### Potential Enhancements (Future)

If more realistic test data is needed in the future:

1. **Lorem Ipsum Content**: Could add methods that generate realistic post content
2. **Realistic Author Names**: Comment factory could generate realistic author names
3. **Date Ranges**: Methods to create posts/comments across date ranges
4. **Attachment Variants**: Methods for different file types (PDF, video, etc.)

These are not required for current functionality but could be useful as the test suite grows.

---

## Conclusion

The `tests/factories/` directory contains **well-designed factory classes** that properly extend WordPress's test infrastructure:

- **PostFactory**: 3 methods for posts with revisions, trash status, and bulk creation
- **AttachmentFactory**: 5 methods for images, alt text, orphaned media, and large files
- **CommentFactory**: 5 methods for spam, trash, threads, and author association

**Key Achievements:**

- All factories properly extend WP*UnitTest_Factory_For*\* classes
- Methods create properly structured WordPress data
- Return values are consistent and useful for assertions
- Documentation is complete with PHPDoc comments

The factories provide a solid foundation for WordPress integration testing and follow WordPress testing best practices.
