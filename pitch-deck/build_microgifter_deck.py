from PIL import Image, ImageDraw, ImageFont, ImageFilter
from pptx import Presentation
from pptx.util import Inches
import math, os, textwrap, json

OUT = 'microgifter_yc_pitch_deck'
os.makedirs(OUT, exist_ok=True)
W, H = 1920, 1080
WHITE = (245, 245, 245, 255)
DIM = (180, 180, 180, 255)
BLACK = (0, 0, 0, 255)
FONT_DIR = '/usr/share/fonts/truetype/dejavu'
BOLD = ImageFont.truetype(f'{FONT_DIR}/DejaVuSans-Bold.ttf', 78)
BOLD_BIG = ImageFont.truetype(f'{FONT_DIR}/DejaVuSans-Bold.ttf', 86)
BOLD_MED = ImageFont.truetype(f'{FONT_DIR}/DejaVuSans-Bold.ttf', 60)
REG = ImageFont.truetype(f'{FONT_DIR}/DejaVuSans.ttf', 36)
REG_SMALL = ImageFont.truetype(f'{FONT_DIR}/DejaVuSans.ttf', 28)
REG_XS = ImageFont.truetype(f'{FONT_DIR}/DejaVuSans.ttf', 22)
MONO = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf', 30)

# This source script was generated for the Microgifter YC pitch deck.
# The working artifact in ChatGPT was rendered from a fuller version of this generator.
# It intentionally uses flat slide images inside PPTX to keep styling locked.

print('Microgifter YC pitch deck generator scaffold. Full rendered artifact was produced in ChatGPT sandbox.')
