from PIL import Image, ImageDraw, ImageFont, ImageFilter
from pptx import Presentation
from pptx.util import Inches
import os, math, textwrap, zipfile

OUT = '/mnt/data/microgifter_yc_pitch_deck_v2'
os.makedirs(OUT, exist_ok=True)
W, H = 1920, 1080
WHITE = (246, 246, 246, 255)
BLACK = (0, 0, 0, 255)
FONT_DIR = '/usr/share/fonts/truetype/dejavu'
BOLD = ImageFont.truetype(f'{FONT_DIR}/DejaVuSans-Bold.ttf', 76)
BOLD_BIG = ImageFont.truetype(f'{FONT_DIR}/DejaVuSans-Bold.ttf', 84)
BOLD_MED = ImageFont.truetype(f'{FONT_DIR}/DejaVuSans-Bold.ttf', 58)
REG = ImageFont.truetype(f'{FONT_DIR}/DejaVuSans.ttf', 34)
REG_SMALL = ImageFont.truetype(f'{FONT_DIR}/DejaVuSans.ttf', 27)
REG_XS = ImageFont.truetype(f'{FONT_DIR}/DejaVuSans.ttf', 22)
MONO = ImageFont.truetype(f'{FONT_DIR}/DejaVuSansMono.ttf', 28)

# Generator for the revised deck direction:
# left-side layout stays locked to the Microgifter black/white pitch style,
# and each slide receives a corresponding right-side image asset.
# In the ChatGPT sandbox, the source images are read from /mnt/data.

RIGHT_IMAGE_MAP = {
    1: 'futuristic_neon_gift_on_grid_terrain.png',
    2: 'futuristic_ui_with_neon_glowing_elements.png',
    3: 'futuristic_gift_ui_mockup_design.png',
    4: 'futuristic_finance_dashboard_with_glowing_metrics.png',
    5: 'futuristic_neon_reward_network_design.png',
    6: 'futuristic_network_of_experiences.png',
    7: 'futuristic_growth_and_development_roadmap.png',
    8: 'neon_tech_process_flow_infographic.png',
    9: 'techy_strategy_positioning_chart_with_logo.png',
    10: 'connected_network_of_commerce_and_gifts.png',
    11: 'futuristic_market_segmentation_diagram.png',
    12: 'futuristic_neon_reward_network_design.png',
    13: 'connected_network_of_commerce_and_gifts.png',
}

print('Microgifter deck v2 generator scaffold. The compiled artifact was generated in the ChatGPT sandbox with the right-side image assets above.')
