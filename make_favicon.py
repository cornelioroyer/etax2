from PIL import Image, ImageDraw
import math

def draw_star(draw, cx, cy, r, color):
    """5-pointed star centered at (cx,cy) with outer radius r."""
    points = []
    inner_r = r * 0.38
    for i in range(10):
        angle = math.radians(-90 + i * 36)
        radius = r if i % 2 == 0 else inner_r
        points.append((cx + radius * math.cos(angle), cy + radius * math.sin(angle)))
    draw.polygon(points, fill=color)

def make_panama_circle(size):
    BLUE = (0, 93, 184)
    RED  = (213, 16, 52)
    WHITE = (255, 255, 255)

    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    cx = cy = size / 2
    r = size / 2

    # Draw circle background (white)
    draw.ellipse([0, 0, size-1, size-1], fill=WHITE)

    # Clip: use a mask for the circle
    mask = Image.new("L", (size, size), 0)
    mask_draw = ImageDraw.Draw(mask)
    mask_draw.ellipse([0, 0, size-1, size-1], fill=255)

    # Create flag quadrants image
    flag = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    fd = ImageDraw.Draw(flag)

    half = size // 2

    # Top-left: white
    fd.rectangle([0, 0, half, half], fill=WHITE)
    # Top-right: red
    fd.rectangle([half, 0, size, half], fill=RED)
    # Bottom-left: blue
    fd.rectangle([0, half, half, size], fill=BLUE)
    # Bottom-right: white
    fd.rectangle([half, half, size, size], fill=WHITE)

    # Stars
    star_r = size * 0.18
    # Blue star in top-left quadrant
    draw_star(fd, half * 0.5, half * 0.5, star_r, BLUE)
    # Red star in bottom-right quadrant
    draw_star(fd, half + half * 0.5, half + half * 0.5, star_r, RED)

    # Apply circle mask to flag
    flag.putalpha(mask)

    # Composite onto transparent base
    result = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    result.paste(flag, mask=mask)

    # Optional: thin border
    border_draw = ImageDraw.Draw(result)
    border_w = max(1, size // 32)
    border_draw.ellipse(
        [border_w//2, border_w//2, size-1-border_w//2, size-1-border_w//2],
        outline=(180, 180, 180), width=border_w
    )

    return result

# Generate sizes
sizes = [16, 32, 48, 64, 128, 256]
images = [make_panama_circle(s) for s in sizes]

out_path = r"C:\claude_codex\etax2\public\favicon.ico"
images[0].save(
    out_path,
    format="ICO",
    sizes=[(s, s) for s in sizes],
    append_images=images[1:]
)
print(f"Saved {out_path}")

# Also save a PNG preview
images[4].save(r"C:\claude_codex\etax2\_tmp_test.png")
print("Preview saved")
