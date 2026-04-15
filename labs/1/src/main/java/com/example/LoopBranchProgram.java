package com.example;

public class LoopBranchProgram {

    public int classifyAndScore(int[] numbers) {
        int score = 0;

        for (int number : numbers) {
            if (number > 0) {
                score += 2;
            } else if (number == 0) {
                score += 1;
            } else {
                score -= 1;
            }
        }

        if (score >= 5) {
            return 1;
        }

        return 0;
    }

    public static void main(String[] args) {
        LoopBranchProgram program = new LoopBranchProgram();
        int result = program.classifyAndScore(new int[]{3, 0, -2, 9});
        System.out.println("Result: " + result);
    }
}
