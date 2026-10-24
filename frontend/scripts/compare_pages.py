import sys
import os
from PIL import Image, ImageChops

def create_side_by_side_slices(live_path, local_path, out_dir, slice_height=1200):
    os.makedirs(out_dir, exist_ok=True)
    live_img = Image.open(live_path).convert("RGB")
    local_img = Image.open(local_path).convert("RGB")
    
    w_live, h_live = live_img.size
    w_local, h_local = local_img.size
    
    max_h = max(h_live, h_local)
    target_w = min(w_live, w_local)
    
    print(f"Live dimensions: {w_live}x{h_live}")
    print(f"Local dimensions: {w_local}x{h_local}")
    
    num_slices = (max_h + slice_height - 1) // slice_height
    
    for i in range(num_slices):
        y0 = i * slice_height
        y1 = min(y0 + slice_height, max_h)
        h = y1 - y0
        
        # Crop live
        box_live = (0, min(y0, h_live), target_w, min(y1, h_live))
        slice_live = live_img.crop(box_live)
        if slice_live.size[1] < h:
            padded_live = Image.new("RGB", (target_w, h), (255, 255, 255))
            padded_live.paste(slice_live, (0, 0))
            slice_live = padded_live
            
        # Crop local
        box_local = (0, min(y0, h_local), target_w, min(y1, h_local))
        slice_local = local_img.crop(box_local)
        if slice_local.size[1] < h:
            padded_local = Image.new("RGB", (target_w, h), (255, 255, 255))
            padded_local.paste(slice_local, (0, 0))
            slice_local = padded_local
            
        # Create side-by-side: [LIVE | LOCAL]
        combined = Image.new("RGB", (target_w * 2, h))
        combined.paste(slice_live, (0, 0))
        combined.paste(slice_local, (target_w, 0))
        
        out_file = os.path.join(out_dir, f"slice_{i:02d}_y{y0}-{y1}.jpg")
        combined.save(out_file, quality=85)
        
        # Calculate diff
        diff = ImageChops.difference(slice_live, slice_local)
        diff_box = diff.getbbox()
        diff_status = "IDENTICAL" if not diff_box else "DIFFERS"
        print(f"Slice {i:02d} (y={y0}..{y1}): {diff_status} -> {out_file}")

if __name__ == "__main__":
    live_path = sys.argv[1]
    local_path = sys.argv[2]
    out_dir = sys.argv[3]
    create_side_by_side_slices(live_path, local_path, out_dir)
